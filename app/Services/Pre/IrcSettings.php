<?php
namespace App\Services\Pre;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * IRC-Einstellungen: Werte aus dem Admin (Tabelle settings) haben Vorrang vor der .env.
 * Leeres Feld im Admin = Wert aus der .env.
 */
class IrcSettings
{
    public const KEYS = ['irc_enabled', 'irc_server', 'irc_port', 'irc_tls', 'irc_nick', 'irc_realname', 'irc_password', 'irc_channels'];

    public static function get(): array
    {
        Setting::flush(); // der Dienst läuft lange: immer frisch lesen
        $c = (array) config('indexer.pre_irc');
        $s = fn (string $k) => Setting::get($k);

        if (($v = $s('irc_server')) !== null) $c['server'] = $v;
        if (($v = $s('irc_port')) !== null) $c['port'] = (int) $v;
        if (($v = $s('irc_tls')) !== null) $c['tls'] = $v === '1';
        if (($v = $s('irc_nick')) !== null) { $c['nick'] = $v; $c['username'] = $v; }
        if (($v = $s('irc_realname')) !== null) $c['realname'] = $v;
        if (($v = $s('irc_channels')) !== null) $c['channels'] = self::channels($v);
        if (($v = $s('irc_password')) !== null) {
            try { $c['password'] = Crypt::decryptString($v); } catch (\Throwable $e) { $c['password'] = null; }
        }
        if (empty($c['realname'])) $c['realname'] = $c['nick'] ?? '';
        $c['enabled'] = $s('irc_enabled') !== '0';
        return $c;
    }

    /** "#a, b\n#c" => ['#a', '#b', '#c'] */
    public static function channels(string $text): array
    {
        $out = [];
        foreach (preg_split('/[\s,]+/', $text, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            $ch = $ch[0] === '#' ? $ch : '#' . $ch;
            if (preg_match('/^#[^\s,\x07]{1,49}$/', $ch)) {
                $out[strtolower($ch)] ??= $ch; // doppelte (Groß/klein egal) nur einmal
            }
        }
        return array_values($out);
    }

    /** Dienst soll neu verbinden (wird vom Dienst alle paar Sekunden geprüft). */
    public static function requestReload(): void
    {
        \Illuminate\Support\Facades\Cache::forever('pre_irc.reload', microtime(true));
    }

    public static function reloadToken(): ?float
    {
        $v = \Illuminate\Support\Facades\Cache::get('pre_irc.reload');
        return $v === null ? null : (float) $v;
    }
}
