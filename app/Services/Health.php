<?php
namespace App\Services;

/**
 * Prüfregeln der Überwachung, ohne Datenbank/Mail, damit sie testbar sind.
 * Eingabe: Messwerte. Ausgabe: [schlüssel => Meldung] für alles, was nicht stimmt.
 */
class Health
{
    public static function problems(array $m, array $cfg): array
    {
        $now = $m['now'];
        $p = [];
        $h = fn (int $s) => intdiv($s, 3600);

        if ($m['active_groups'] > 0 && $m['nntp_configured']) {
            $last = $m['release_updated_at'] ?? $m['first_seen'];
            if ($last && $now - $last > $cfg['crawl_hours'] * 3600) {
                $p['crawl'] = sprintf('Der Crawler hat seit %d Stunden keine Releases mehr gefunden oder aktualisiert. Prüfen: php artisan indexer:status und php artisan indexer:test-nntp', $h($now - $last));
            }
        }
        if ($m['irc_enabled'] && $m['irc_down_since'] && $now - $m['irc_down_since'] > $cfg['irc_minutes'] * 60) {
            $p['irc'] = sprintf('Der IRC-Pre-Scraper ist seit %d Minuten nicht verbunden.%s Prüfen: systemctl status drstr4nge-pre',
                intdiv($now - $m['irc_down_since'], 60), $m['irc_error'] ? ' Letzter Fehler: ' . $m['irc_error'] . '.' : '');
        }
        if ($m['disk_percent'] !== null && $m['disk_percent'] >= $cfg['disk_percent']) {
            $p['disk'] = sprintf('Die Festplatte ist zu %s %% voll (%s frei).', rtrim(rtrim(number_format($m['disk_percent'], 1, ',', ''), '0'), ','), $m['disk_free']);
        }
        if ($m['backup_enabled']) {
            $last = $m['backup_at'] ?? null;
            $since = $last ?? $m['first_seen'];
            if ($since && $now - $since > 26 * 3600) {
                $p['backup'] = $last
                    ? sprintf('Die letzte Sicherung ist %d Stunden alt.', $h($now - $last))
                    : 'Es wurde noch keine Sicherung erstellt.';
            }
            if (!empty($m['backup_error'])) {
                $p['backup'] = trim(($p['backup'] ?? '') . ' Fehler bei der Sicherung: ' . $m['backup_error']);
            }
        }
        return $p;
    }
}
