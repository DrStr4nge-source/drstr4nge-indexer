<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Predb;
use App\Models\Release;
use App\Models\Setting;
use App\Services\Pre\IrcSettings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PreController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $pres = Predb::query()
            ->when($q !== '', function ($query) use ($q) {
                foreach (preg_split('/[\s.]+/', $q, -1, PREG_SPLIT_NO_EMPTY) as $w) {
                    $query->where('title', 'like', '%' . addcslashes($w, '%_') . '%');
                }
            })
            ->orderByDesc('predate')->paginate(100)->withQueryString();

        $connected = Cache::get('pre_irc.connected_at');
        $last = Cache::get('pre_irc.last_at');
        $api = Cache::get('predb_api.last_at');
        $rss = Cache::get('pre_rss.last_at');

        return view('admin.predb', [
            'pres' => $pres,
            'q' => $q,
            'total' => \App\Support\Stats::remember('pres_total', fn () => Predb::count()),
            'today' => \App\Support\Stats::remember('pres_today', fn () => Predb::where('predate', '>=', now()->startOfDay())->count(), 120),
            'linked' => \App\Support\Stats::remember('pres_linked', fn () => Release::whereNotNull('pre_id')->count()),
            'connectedAt' => $connected ? \Illuminate\Support\Carbon::createFromTimestamp($connected) : null,
            'lastAt' => $last ? \Illuminate\Support\Carbon::createFromTimestamp($last) : null,
            'apiAt' => $api ? \Illuminate\Support\Carbon::createFromTimestamp($api) : null,
            'rssAt' => $rss ? \Illuminate\Support\Carbon::createFromTimestamp($rss) : null,
            'rssEnabled' => (bool) config('indexer.pre_rss'),
            'apiEnabled' => (bool) config('indexer.predb_api.enabled'),
            'irc' => $irc = IrcSettings::get(),
            'configured' => !empty($irc['nick']) && $irc['enabled'],
            'ircError' => Cache::get('pre_irc.error'),
            'hasPassword' => !empty($irc['password']),
        ]);
    }

    public function updateIrc(Request $request)
    {
        $data = $request->validate([
            'irc_server' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'irc_port' => 'required|integer|min:1|max:65535',
            'irc_nick' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z\[\]\\`_^{|}][A-Za-z0-9\[\]\\`_^{|}\-]*$/'],
            'irc_realname' => ['nullable', 'string', 'max:60', 'not_regex:/[\x00-\x1F\x7F]/'],
            'irc_channels' => 'required|string|max:1000',
            'irc_password' => ['nullable', 'string', 'max:255', 'not_regex:/[\x00-\x1F\x7F]/'],
            'remove_password' => 'nullable|boolean',
        ], [
            'irc_server.regex' => 'Nur Servername oder IP, ohne Port und ohne "irc://".',
            'irc_realname.not_regex' => 'Keine Zeilenumbrüche oder Steuerzeichen.',
            'irc_password.not_regex' => 'Keine Zeilenumbrüche oder Steuerzeichen.',
            'irc_nick.regex' => 'Nick: Buchstaben, Ziffern und _ - [ ] { } | ^ `, kein Leerzeichen, nicht mit Ziffer beginnen.',
        ]);
        $channels = IrcSettings::channels($data['irc_channels']);
        if (!$channels) {
            return back()->withInput()->withErrors(['irc_channels' => 'Mindestens einen gültigen Kanal eintragen, z.B. #PreNNTmux']);
        }
        Setting::put('irc_enabled', $request->boolean('irc_enabled') ? '1' : '0');
        Setting::put('irc_server', trim($data['irc_server']));
        Setting::put('irc_port', (string) (int) $data['irc_port']);
        Setting::put('irc_tls', $request->boolean('irc_tls') ? '1' : '0');
        Setting::put('irc_nick', $data['irc_nick']);
        Setting::put('irc_realname', trim((string) ($data['irc_realname'] ?? '')) ?: null);
        Setting::put('irc_channels', implode(',', $channels));
        if ($request->boolean('remove_password')) {
            Setting::put('irc_password', null);
        } elseif (($data['irc_password'] ?? '') !== '') {
            Setting::put('irc_password', Crypt::encryptString($data['irc_password']));
        }
        IrcSettings::requestReload();
        return redirect()->route('admin.predb')->with('status', 'IRC-Einstellungen gespeichert. Der Scraper verbindet sich in wenigen Sekunden neu.');
    }

    public function reconnect()
    {
        IrcSettings::requestReload();
        return redirect()->route('admin.predb')->with('status', 'Der Scraper verbindet sich in wenigen Sekunden neu.');
    }

    /** Admin-Werte löschen, wieder die .env verwenden */
    public function resetIrc()
    {
        foreach (IrcSettings::KEYS as $k) {
            Setting::put($k, null);
        }
        IrcSettings::requestReload();
        return redirect()->route('admin.predb')->with('status', 'IRC-Einstellungen zurückgesetzt, es gelten wieder die Werte aus der .env.');
    }
}
