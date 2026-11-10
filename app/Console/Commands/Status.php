<?php
namespace App\Console\Commands;

use App\Models\Newsgroup;
use App\Models\Predb;
use App\Models\Release;
use App\Support\CrawlLock;
use App\Support\Format;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Alles auf einen Blick: läuft ein Crawl, Stand je Gruppe, Releases, Pre, Sicherung, letzte Läufe. */
class Status extends Command
{
    protected $signature = 'indexer:status';
    protected $description = 'Zeigt, was der Indexer gerade macht';

    public function handle(): int
    {
        $ago = fn ($ts) => $ts ? Format::ago(Carbon::createFromTimestamp((int) $ts)) : 'noch nie';
        $groups = Newsgroup::orderBy('name')->get();
        $running = CrawlLock::running($groups->pluck('id'));

        $this->line('<options=bold>Crawler</>');
        if ($running) {
            foreach ($running as $p) {
                $this->line(sprintf('  läuft: %s bei Artikel %s von %s (seit %s, Prozess %d)', $p['name'],
                    number_format($p['at'], 0, ',', '.'), number_format($p['high'], 0, ',', '.'), $ago($p['started']), $p['pid']));
            }
        } else {
            $this->line('  läuft gerade nicht. Letzter Lauf: ' . $ago(Cache::get('schedule.last.crawl')));
        }

        $this->newLine();
        $this->line('<options=bold>Newsgroups</>');
        $this->table(['Gruppe', 'Aktiv', 'Bis Artikel', 'Zuletzt gelesen', 'Releases'], $groups->map(fn ($g) => [
            $g->name, $g->active ? 'ja' : 'nein',
            $g->last_article ? number_format($g->last_article, 0, ',', '.') : '0 (wird neu gelesen)',
            $g->updated_at ? Format::ago($g->updated_at) : '–',
            number_format(Release::where('newsgroup_id', $g->id)->count(), 0, ',', '.'),
        ])->all());

        $this->line('<options=bold>Releases</>');
        $this->line(sprintf('  gesamt %s, sichtbar %s, verschleiert %s, neu heute %s, mit Pre %s',
            ...array_map(fn ($n) => number_format($n, 0, ',', '.'), [
                Release::count(), Release::visible()->count(), Release::where('obfuscated', true)->count(),
                Release::where('created_at', '>=', now()->startOfDay())->count(), Release::whereNotNull('pre_id')->count(),
            ])));
        $this->line('  zuletzt geändert: ' . (($t = Release::max('updated_at')) ? Format::ago(Carbon::parse($t)) : '–'));

        $this->newLine();
        $this->line('<options=bold>Pre-Daten</>');
        $irc = Cache::get('pre_irc.connected_at');
        $this->line('  IRC: ' . ($irc ? 'verbunden seit ' . $ago($irc) : 'nicht verbunden' . (($e = Cache::get('pre_irc.error')) ? " ($e)" : '')));
        $this->line(sprintf('  Einträge %s, letzter Pre %s, predb.net %s', number_format(Predb::count(), 0, ',', '.'),
            $ago(Cache::get('pre_irc.last_at')), $ago(Cache::get('predb_api.last_at'))));

        $this->newLine();
        $this->line('<options=bold>Sicherung</>');
        $this->line('  letzte: ' . $ago(Cache::get('backup.last_at')) . (($s = Cache::get('backup.last_size')) ? ' (' . Format::bytes($s) . ')' : ''));
        if ($e = Cache::get('backup.error')) {
            $this->line("  <fg=red>Fehler: $e</>");
        }

        $this->newLine();
        $this->line('<options=bold>Zeitplan (letzte Läufe)</>');
        foreach (['crawl' => 'Crawler', 'inspect' => 'Prüfen (PAR2/RAR)', 'metadata' => 'Metadaten', 'pre-fetch' => 'Pre holen', 'cleanup' => 'Aufräumen', 'optimize' => 'Tabelle verkleinern', 'health' => 'Überwachung'] as $k => $label) {
            $this->line(sprintf('  %-20s %s', $label, $ago(Cache::get("schedule.last.$k"))));
        }
        if (!Cache::get('schedule.last.health') && !Cache::get('schedule.last.crawl')) {
            $this->warn('  Der Zeitplan scheint nicht zu laufen. Cronjob prüfen: cat /etc/cron.d/usenet-indexer');
        }
        return self::SUCCESS;
    }
}
