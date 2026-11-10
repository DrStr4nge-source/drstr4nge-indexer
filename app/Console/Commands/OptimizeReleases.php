<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gibt gelöschten Platz der Tabelle releases an die Festplatte zurück (OPTIMIZE TABLE = Neuaufbau).
 * Läuft nur, wenn es sich lohnt, genug Platz auf der Platte ist und die Tabelle innerhalb von 60 s frei wird.
 * So kann er den Indexer nicht blockieren (sonst warten alle Abfragen hinter ihm).
 */
class OptimizeReleases extends Command
{
    protected $signature = 'indexer:optimize
        {--force : auch ohne Mindestmenge und Abstand}
        {--if-due : nur wenn seit dem letzten Lauf INDEXER_OPTIMIZE_DAYS vergangen sind (für den Zeitplan)}';
    protected $description = 'Datei der Tabelle releases verkleinern (freien Platz nach dem Aufräumen zurückgeben)';

    public function handle(): int
    {
        if (!in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->info('Nur für MariaDB/MySQL.');
            return self::SUCCESS;
        }
        $days = max(1, (int) config('indexer.optimize.days'));
        $last = (int) Cache::get('optimize.last', 0);
        if ($this->option('if-due') && !$this->option('force') && time() - $last < $days * 86400 - 3600) {
            return self::SUCCESS; // noch nicht wieder dran
        }

        $db = DB::connection()->getDatabaseName();
        $this->refreshStats();
        $t = DB::selectOne('SELECT data_length + index_length AS size, data_free AS free FROM information_schema.tables WHERE table_schema = ? AND table_name = ?', [$db, 'releases']);
        if (!$t) {
            return self::SUCCESS;
        }
        $size = (int) $t->size;
        $free = (int) $t->free;
        $gb = fn (int $b) => \App\Support\Format::bytes($b);
        $this->line(sprintf('Tabelle releases: %s, davon %s frei.', $gb($size + $free), $gb($free)));

        $minFree = (int) config('indexer.optimize.min_free_mb') * 1024 * 1024;
        if (!$this->option('force') && ($free < $minFree || $free < 0.10 * ($size + $free))) {
            $this->info('Lohnt sich noch nicht (mindestens ' . $gb($minFree) . ' und 10 % frei).');
            return self::SUCCESS;
        }

        // Neue Kopie braucht etwa so viel Platz wie die belegten Daten, plus Reserve
        $datadir = (string) (DB::selectOne('SELECT @@datadir AS d')->d ?? '');
        $disk = $datadir !== '' ? @disk_free_space(dirname(rtrim($datadir, '/'))) : false;
        if ($disk !== false && $disk < $size * 1.2) {
            $msg = sprintf('OPTIMIZE übersprungen: zu wenig Platz auf der Platte (%s frei, nötig etwa %s).', $gb((int) $disk), $gb((int) ($size * 1.2)));
            $this->warn($msg);
            Log::warning('Datenbank: ' . $msg);
            return self::SUCCESS;
        }

        $this->info('Baue die Tabelle neu auf. Das kann Stunden dauern, Crawler und Webseite laufen weiter.');
        $start = time();
        try {
            // Nicht ewig auf die Tabelle warten: sonst stehen alle anderen Abfragen hinter uns in der Schlange
            DB::statement('SET SESSION lock_wait_timeout = ' . max(5, (int) config('indexer.optimize.lock_wait')));
            $rows = DB::select('OPTIMIZE TABLE releases');
            // MariaDB meldet Fehler (z.B. Tabelle belegt) als Ergebniszeile, nicht als Ausnahme
            foreach ($rows as $r) {
                $r = array_change_key_case((array) $r, CASE_LOWER);
                if (strtolower((string) ($r['msg_type'] ?? '')) === 'error') {
                    throw new \RuntimeException((string) ($r['msg_text'] ?? 'unbekannter Fehler'));
                }
            }
        } catch (\Throwable $e) {
            $msg = str_contains($e->getMessage(), 'Lock wait timeout') || str_contains($e->getMessage(), 'metadata lock')
                ? 'OPTIMIZE abgebrochen: Tabelle war belegt, nächster Versuch beim nächsten Lauf.'
                : 'OPTIMIZE fehlgeschlagen: ' . mb_substr($e->getMessage(), 0, 200);
            $this->warn($msg);
            Log::warning('Datenbank: ' . $msg);
            return self::SUCCESS;
        }
        $this->refreshStats();
        $after = DB::selectOne('SELECT data_length + index_length + data_free AS total FROM information_schema.tables WHERE table_schema = ? AND table_name = ?', [$db, 'releases']);
        Cache::forever('optimize.last', time());
        $msg = sprintf('OPTIMIZE fertig in %d Min: %s → %s.', intdiv(time() - $start, 60), $gb($size + $free), $gb((int) ($after->total ?? 0)));
        $this->info($msg);
        Log::info('Datenbank: ' . $msg);
        return self::SUCCESS;
    }

    /** Größenangaben in information_schema sind sonst veraltet (MariaDB aktualisiert sie nur gelegentlich) */
    private function refreshStats(): void
    {
        try {
            DB::select('ANALYZE TABLE releases');
        } catch (\Throwable) {
            // nur für die Anzeige
        }
    }
}
