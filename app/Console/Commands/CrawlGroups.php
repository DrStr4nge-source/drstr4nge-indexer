<?php
namespace App\Console\Commands;

use App\Models\Newsgroup;
use App\Services\Nntp;
use App\Services\ReleaseBuilder;
use App\Support\ProcessPool;
use Illuminate\Console\Command;

class CrawlGroups extends Command
{
    protected $signature = 'indexer:crawl
        {--batch=20000 : Artikel pro Abruf}
        {--group= : Nur diese Gruppe (ID oder Name)}
        {--connections= : Gleichzeitige Verbindungen (Standard: NNTP_CONNECTIONS)}';
    protected $description = 'Holt neue Header aller aktiven Newsgroups und baut Releases (mehrere Gruppen parallel)';

    public function handle(Nntp $nntp, ReleaseBuilder $builder): int
    {
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

        $connections = max(1, (int) ($this->option('connections') ?: config('indexer.nntp.connections', 1)));
        if (!$only && $connections > 1 && $groups->count() > 1) {
            return $this->parallel($groups->all(), min($connections, $groups->count()));
        }
        return $this->crawl($nntp, $builder, $groups->all());
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

    private function crawl(Nntp $nntp, ReleaseBuilder $builder, array $groups): int
    {
        try {
            $nntp->connect();
        } catch (\RuntimeException $e) {
            $this->error('Verbindung fehlgeschlagen: ' . $e->getMessage() . ' (Test: php artisan indexer:test-nntp)');
            return self::FAILURE;
        }
        $batch = max(1000, (int) $this->option('batch'));
        $ok = true;

        foreach ($groups as $g) {
            try {
                [$low, $high] = $nntp->group($g->name);
                $start = $g->last_article > 0
                    ? max($g->last_article + 1, $low)
                    : max($low, $high - (int) config('indexer.backfill'));
                if ($start > $high) {
                    $this->info("{$g->name}: nichts Neues");
                }
                while ($start <= $high) {
                    $end = min($start + $batch - 1, $high);
                    $n = $builder->ingest($g, $nntp->xover($start, $end));
                    $g->update(['last_article' => $end]);
                    $this->info("{$g->name}: $start-$end ($n Releases aktualisiert)");
                    $start = $end + 1;
                }
            } catch (\RuntimeException $e) {
                // Eine kaputte Gruppe stoppt nicht die anderen
                $this->error("{$g->name}: " . $e->getMessage());
                $ok = false;
            }
        }
        $nntp->close();
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
