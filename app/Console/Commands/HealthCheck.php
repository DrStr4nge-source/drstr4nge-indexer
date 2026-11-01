<?php
namespace App\Console\Commands;

use App\Models\Newsgroup;
use App\Models\Release;
use App\Models\User;
use App\Services\Health;
use App\Services\Pre\IrcSettings;
use App\Support\Format;
use App\Support\SystemStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/** Überwachung (alle 15 Minuten): schickt eine Mail, wenn etwas nicht stimmt, und eine, wenn es wieder läuft. */
class HealthCheck extends Command
{
    protected $signature = 'indexer:health {--test : Test-Mail schicken}';
    protected $description = 'Prüft Crawler, IRC, Festplatte und Sicherung und warnt per Mail';

    public function handle(): int
    {
        $to = $this->recipients();
        if ($this->option('test')) {
            if (!$to) {
                $this->error('Kein Empfänger: ALERT_EMAIL in der .env setzen oder einen Admin anlegen.');
                return self::FAILURE;
            }
            $this->send($to, 'Test', "Wenn du diese Mail liest, kommen Warnungen bei dir an.");
            $this->info('Test-Mail an ' . implode(', ', $to) . ' geschickt (falls MAIL_MAILER=log: steht in storage/logs/laravel.log).');
            return self::SUCCESS;
        }

        $now = time();
        $first = Cache::rememberForever('health.first_seen', fn () => $now);
        $irc = IrcSettings::get();
        $ircOn = $irc['enabled'] && !empty($irc['nick']);
        if (!$ircOn || Cache::get('pre_irc.connected_at')) {
            Cache::forget('health.irc_down_since');
            $down = null;
        } else {
            $down = Cache::rememberForever('health.irc_down_since', fn () => $now);
        }
        $disk = SystemStats::usage((int) @disk_total_space(base_path()), (int) @disk_total_space(base_path()) - (int) @disk_free_space(base_path()));
        $updated = Release::max('updated_at');

        $problems = Health::problems([
            'now' => $now, 'first_seen' => $first,
            'active_groups' => Newsgroup::where('active', true)->count(),
            'nntp_configured' => (bool) config('indexer.nntp.host'),
            'release_updated_at' => $updated ? strtotime((string) $updated . ' ' . config('app.timezone')) : null,
            'irc_enabled' => $ircOn, 'irc_down_since' => $down, 'irc_error' => Cache::get('pre_irc.error'),
            'disk_percent' => $disk['percent'] ?? null, 'disk_free' => $disk ? Format::bytes($disk['total'] - $disk['used']) : '',
            'backup_enabled' => (bool) config('indexer.backup.enabled'), 'backup_at' => Cache::get('backup.last_at'), 'backup_error' => Cache::get('backup.error'),
        ], config('indexer.alerts'));
        Cache::forever('health.problems', $problems);

        // Pro Warnung: at = letzte Problem-Mail, open = Problem besteht, notified = für dieses Auftreten wurde gemailt.
        // Höchstens eine Problem-Mail pro repeat_hours, auch wenn ein Wert ständig um die Schwelle pendelt. "Wieder in
        // Ordnung" nur, wenn vorher für dieses Auftreten eine Problem-Mail ging.
        $sent = [];
        foreach ((array) Cache::get('health.sent', []) as $k => $v) {
            $sent[$k] = is_array($v) ? $v : ['at' => (int) $v, 'open' => true, 'notified' => true]; // altes Format: nur Zeitpunkt
        }
        $repeat = (int) config('indexer.alerts.repeat_hours') * 3600;
        $new = [];
        foreach ($problems as $k => $t) {
            $st = $sent[$k] ?? ['at' => 0, 'open' => false, 'notified' => false];
            if (!$st['open']) {
                \Illuminate\Support\Facades\Log::warning('Überwachung: ' . $t);
            }
            if ($now - $st['at'] > $repeat) {
                $new[$k] = $t;
            }
            $sent[$k] = ['at' => $st['at'], 'open' => true, 'notified' => $st['open'] && $st['notified']];
        }
        $fixed = array_filter($sent, fn ($st, $k) => $st['open'] && !isset($problems[$k]), ARRAY_FILTER_USE_BOTH);
        if ($fixed) {
            \Illuminate\Support\Facades\Log::info('Überwachung: wieder in Ordnung: ' . implode(', ', array_keys($fixed)));
        }
        if ($to && $new) {
            $this->send($to, count($new) === 1 ? 'Problem' : count($new) . ' Probleme', implode("\n\n", array_map(fn ($t) => "- $t", $new)));
            foreach ($new as $k => $_) {
                $sent[$k]['at'] = $now;
                $sent[$k]['notified'] = true;
            }
        }
        $notify = array_filter($fixed, fn ($st) => $st['notified']);
        if ($to && $notify) {
            $names = ['crawl' => 'Crawler', 'irc' => 'IRC-Pre-Scraper', 'disk' => 'Festplatte', 'backup' => 'Sicherung'];
            $this->send($to, 'Wieder in Ordnung', 'Wieder in Ordnung: ' . implode(', ', array_map(fn ($k) => $names[$k] ?? $k, array_keys($notify))) . '.');
        }
        foreach ($sent as $k => $st) {
            if (isset($fixed[$k])) {
                $sent[$k] = ['at' => $st['at'], 'open' => false, 'notified' => false];
            }
            if (!$sent[$k]['open'] && $now - $sent[$k]['at'] > $repeat) {
                unset($sent[$k]); // alt und erledigt
            }
        }
        Cache::forever('health.sent', $sent);

        foreach ($problems as $t) {
            $this->warn($t);
        }
        if (!$problems) {
            $this->info('Alles in Ordnung.');
        }
        if (!$to && $problems) {
            $this->warn('Keine Mail verschickt: ALERT_EMAIL fehlt und es gibt keinen Admin.');
        }
        return self::SUCCESS;
    }

    private function recipients(): array
    {
        $list = array_filter(array_map('trim', explode(',', (string) config('indexer.alerts.email'))));
        return $list ?: User::where('is_admin', true)->pluck('email')->all();
    }

    private function send(array $to, string $subject, string $body): void
    {
        $site = \App\Support\Brand::name();
        try {
            Mail::raw("$body\n\n-- \n$site, " . url('/admin'), function ($m) use ($to, $subject, $site) {
                $m->to($to)->subject("[$site] $subject");
            });
        } catch (\Throwable $e) {
            $this->error('Mail nicht verschickt: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('Warn-Mail nicht verschickt: ' . $e->getMessage());
        }
    }
}
