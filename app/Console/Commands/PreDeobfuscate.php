<?php
namespace App\Console\Commands;

use App\Models\Predb;
use App\Models\Release;
use App\Services\Pre\PreMatcher;
use App\Services\ReleaseBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/** Verschleierte Releases über die PreDB benennen (Größe, Dateianzahl, Zeitfenster, nur eindeutige Treffer). */
class PreDeobfuscate extends Command
{
    protected $signature = 'indexer:pre-deobfuscate {--limit=500} {--dry-run : nur anzeigen, nichts ändern}';
    protected $description = 'Verschleierte Releases mit der PreDB auflösen';

    public function handle(ReleaseBuilder $builder): int
    {
        if (!config('indexer.pre_deobfuscate')) {
            $this->info('Aus (PRE_DEOBFUSCATE=false).');
            return self::SUCCESS;
        }
        $rels = Release::where('obfuscated', true)->where('locked', false)
            ->where('completion', '>=', config('indexer.min_completion'))
            ->where('created_at', '>=', now()->subDays(14))
            ->where(fn ($q) => $q->whereNull('pre_checked_at')->orWhere('pre_checked_at', '<', now()->subHours(6)))
            ->orderByDesc('id')->limit((int) $this->option('limit'))->get();
        $found = 0;
        foreach ($rels as $rel) {
            $m = PreMatcher::measure(['real_size' => $rel->real_size, 'real_files' => $rel->real_files, 'files' => $rel->files]);
            if ($m) {
                $tol = PreMatcher::tolerance($m['size'], $m['exact']);
                $posted = $rel->posted_at ?? $rel->created_at;
                // Dateianzahl schon in der Abfrage prüfen (gleiche Regel wie PreMatcher::pick), damit das Limit
                // keine passenden Pres abschneidet. Mehr als 50 Treffer = nicht eindeutig, dann lieber nichts.
                $d = $m['exact'] ? 3 : 2;
                $cands = Predb::whereBetween('size', [$m['size'] - $tol, $m['size'] + $tol])
                    ->whereBetween('predate', [$posted->copy()->subDays(3), $posted->copy()->addHours(2)])
                    ->where(function ($q) use ($m, $d) {
                        $q->whereBetween('files', [max(1, $m['files'] - $d), $m['files'] + $d]);
                        if ($m['exact']) {
                            $q->orWhereNull('files')->orWhere('files', 0);
                        }
                    })
                    ->limit(51)->get(['id', 'size', 'files', 'title'])->toArray();
                if (count($cands) <= 50 && ($pre = PreMatcher::pick($m, $cands))) {
                    $found++;
                    $this->line(sprintf('%d: %s (%s, %d Dateien%s)', $rel->id, $pre['title'], \App\Support\Format::bytes($m['size']), $m['files'], $m['exact'] ? ', PAR2' : ''));
                    if (!$this->option('dry-run')) {
                        $rel->name = $pre['title'];
                        $rel->obfuscated = false;
                        $builder->classify($rel);
                        Log::info("Pre-Auflösung: Release {$rel->id} -> {$pre['title']}");
                    }
                }
            }
            if (!$this->option('dry-run')) {
                $rel->pre_checked_at = now();
                $rel->save();
            }
        }
        $this->info(sprintf('%d verschleierte Releases geprüft, %d über die PreDB benannt.', $rels->count(), $found));
        return self::SUCCESS;
    }
}
