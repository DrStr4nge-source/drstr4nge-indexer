<?php
namespace App\Console\Commands;

use App\Models\Newsgroup;
use App\Services\Backfill;
use App\Services\Nntp;
use App\Services\NntpConnectionLost;
use App\Services\ReleaseBuilder;
use App\Support\CrawlLock;
use App\Support\ProcessPool;
use Illuminate\Console\Command;

class CrawlGroups extends Command
{
    protected $signature = 'indexer:crawl
        {--batch=20000 : Artikel pro Abruf}
        {--group= : Nur diese Gruppe (ID oder Name)}
        {--connections= : Gleichzeitige Verbindungen (Standard: NNTP_CONNECTIONS)}
        {--unlock : hängende Sperren eines abgestürzten Crawls lösen und beenden}';
    protected $description = 'Holt neue Header aller aktiven Newsgroups und baut Releases (mehrere Gruppen parallel)';

    public function handle(Nntp $nntp, ReleaseBuilder $builder): int
    {
        if ($this->option('unlock')) {
            CrawlLock::forceRelease();
            $this->info('Sperren gelöst. Nur nutzen, wenn wirklich kein Crawl mehr läuft (ps aux | grep indexer:crawl).');
            return self::SUCCESS;
        }
        if ($only = $this->option('group')) {
            $groups = Newsgroup::where('id', ctype_digit((string) $only) ? (int) $only : 0)->orWhere('name', $only)->get();
            if ($groups->isEmpty()) {
                $this->error("Gruppe \"$only\" nicht gefunden.");
                return self::FAILURE;
            }
        } else {
            $groups = Newsgroup::where('active', true)->orderBy('id')->get();
        }
        if ($groups->isEmpty()) {
            $this->info('Keine aktiven Newsgroups. Im Admin-Bereich unter "Newsgroups" eintragen.');
            return self::SUCCESS;
        }

        // Gesamtlauf nur einmal gleichzeitig (Cronjob und Handstart). Einzelne Gruppen sperren sich in crawl() selbst.
        $all = null;
        if (!$only) {
            $all = CrawlLock::all();
            if (!$all) {
                $this->warn('Es läuft schon ein Crawl (z.B. über den Cronjob). Stand: php artisan indexer:status');
                return self::SUCCESS;
            }
        }
        try {
            $connections = max(1, (int) ($this->option('connections') ?: config('indexer.nntp.connections', 1)));
            if (!$only && $connections > 1 && $groups->count() > 1) {
                return $this->parallel($groups->all(), min($connections, $groups->count()));
            }
            return $this->crawl($nntp, $builder, $groups->all());
        } finally {
            $all?->release();
        }
    }

    /** Jede Gruppe in einem eigenen Prozess mit eigener Verbindung, höchstens $n gleichzeitig. */
    private function parallel(array $groups, int $n): int
    {
        $this->info(sprintf('Crawle %d Gruppen mit %d Verbindungen gleichzeitig.', count($groups), $n));
        $jobs = [];
        foreach ($groups as $g) {
            $jobs[$g->name] = [PHP_BINARY, base_path('artisan'), 'indexer:crawl', '--group=' . $g->id, '--batch=' . (int) $this->option('batch')];
        }
        $codes = ProcessPool::run($jobs, $n, function (string $name, string $line, bool $isError) {
            $isError ? $this->error("[$name] $line") : $this->line("[$name] $line");
        }, base_path());

        $failed = array_keys(array_filter($codes, fn ($c) => $c !== 0));
        if ($failed) {
            $this->error('Fehler bei: ' . implode(', ', $failed));
            return self::FAILURE;
        }
        $this->info('Alle Gruppen fertig.');
        return self::SUCCESS;
    }

    /** Startpunkt beim ersten Crawl einer Gruppe: nach Tagen (INDEXER_BACKFILL_DAYS) oder nach Anzahl (INDEXER_BACKFILL). */
    private function firstStart(Nntp $nntp, string $name, int $low, int $high): int
    {
        $byCount = max($low, $high - (int) config('indexer.backfill'));
        $days = (int) config('indexer.backfill_days');
        if ($days <= 0) {
            return $byCount;
        }
        $probe = function (int $n) use ($nntp, $name, $high): ?array {
            try {
                foreach ($this->retry($nntp, $name, fn () => $nntp->xover($n, min($n + 200, $high))) as $row) {
                    $ts = isset($row[3]) ? strtotime((string) $row[3]) : false;
                    if ($ts) {
                        return [(int) $row[0], $ts];
                    }
                }
            } catch (NntpConnectionLost $e) {
                throw $e; // Server dauerhaft weg: nicht als "keine Artikel" werten, sonst würde ab Gruppenanfang gelesen
            } catch (\RuntimeException $e) {
                // z.B. "keine Artikel in diesem Bereich"
            }
            return null;
        };
        $start = Backfill::findStart($probe, $low, $high, time() - $days * 86400);
        $this->info(sprintf('%s: Backfill %d Tage ab Artikel %s (etwa %s Artikel)', $name, $days,
            number_format($start, 0, ',', '.'), number_format($high - $start + 1, 0, ',', '.')));
        return $start;
    }

    private function crawl(Nntp $nntp, ReleaseBuilder $builder, array $groups): int
    {
        try {
            $nntp->connect();
        } catch (\RuntimeException $e) {
            $this->error('Verbindung fehlgeschlagen: ' . $e->getMessage() . ' (Test: php artisan indexer:test-nntp)');
            \Illuminate\Support\Facades\Log::error('Crawler: Verbindung zum Usenet-Server fehlgeschlagen: ' . $e->getMessage());
            return self::FAILURE;
        }
        $batch = max(1000, (int) $this->option('batch'));
        $ok = true;

        foreach ($groups as $g) {
            $lock = CrawlLock::group((int) $g->id);
            if (!$lock) {
                $this->warn("{$g->name}: wird gerade schon gelesen, übersprungen");
                continue;
            }
            try {
                [$low, $high] = $this->retry($nntp, null, fn () => $nntp->group($g->name));
                $start = $g->last_article > 0
                    ? max($g->last_article + 1, $low)
                    : $this->firstStart($nntp, $g->name, $low, $high);
                if ($start > $high) {
                    $this->info("{$g->name}: nichts Neues");
                }
                while ($start <= $high) {
                    $end = min($start + $batch - 1, $high);
                    $rows = $this->retry($nntp, $g->name, fn () => $nntp->xover($start, $end));
                    $n = $builder->ingest($g, $rows);
                    $g->update(['last_article' => $end]);
                    CrawlLock::progress((int) $g->id, $g->name, $end, $high);
                    $this->info("{$g->name}: $start-$end ($n Releases aktualisiert)");
                    $start = $end + 1;
                }
            } catch (\RuntimeException $e) {
                // Eine kaputte Gruppe stoppt nicht die anderen
                $this->error("{$g->name}: " . $e->getMessage());
                \Illuminate\Support\Facades\Log::warning("Crawler: {$g->name}: " . $e->getMessage());
                $ok = false;
            } finally {
                CrawlLock::done((int) $g->id);
                $lock->release();
            }
        }
        $nntp->close();
        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Führt einen NNTP-Befehl aus. Trennt der Server die Verbindung, wird neu verbunden,
     * die Gruppe neu gewählt und der Befehl wiederholt (bis zu 3 Mal, mit wachsender Pause).
     */
    private function retry(Nntp $nntp, ?string $group, callable $fn): mixed
    {
        for ($try = 1; ; $try++) {
            try {
                return $fn();
            } catch (NntpConnectionLost $e) {
                if ($try > 3) {
                    throw $e;
                }
                $wait = $try * self::$retryPause;
                $this->line(sprintf('%s: %s, verbinde neu in %d s (Versuch %d von 3)', $group ?? 'Server', $e->getMessage(), $wait, $try));
                $nntp->close();
                if ($wait > 0) {
                    sleep($wait);
                }
                try {
                    $nntp->connect();
                    if ($group !== null) {
                        $nntp->group($group);
                    }
                } catch (\RuntimeException $e2) {
                    $this->line(($group ?? 'Server') . ': Neuverbinden fehlgeschlagen: ' . $e2->getMessage());
                }
            }
        }
    }

    /** Sekunden Pause vor dem 1. Neuverbinden (2. Versuch doppelt, 3. dreifach). In Tests 0. */
    public static int $retryPause = 5;
}
