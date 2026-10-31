<?php
namespace App\Console\Commands;

use App\Models\Release;
use App\Services\Classifier;
use App\Services\Nntp;
use App\Services\Par2;
use App\Services\RarInspector;
use App\Services\ReleaseBuilder;
use App\Services\Yenc;
use App\Services\Obfuscation;
use Illuminate\Console\Command;

class InspectReleases extends Command
{
    protected $signature = 'indexer:inspect {--limit= : Releases pro Lauf}';
    protected $description = 'Löst verschleierte Namen per PAR2 auf und erkennt passwortgeschützte RARs';

    public function handle(Nntp $nntp, ReleaseBuilder $builder): int
    {
        $limit = (int) ($this->option('limit') ?: config('indexer.inspect_per_run'));
        $todo = Release::where('inspected', false)
            ->where('completion', '>=', config('indexer.min_completion'))
            ->orderBy('id')->limit($limit)->get();
        if ($todo->isEmpty()) {
            $this->info('Nichts zu prüfen.');
            return self::SUCCESS;
        }

        $nntp->connect();
        foreach ($todo as $rel) {
            try {
                $this->inspect($nntp, $rel, $builder);
            } catch (\RuntimeException $e) { // Netzwerkfehler: Rest beim nächsten Lauf
                $this->error($e->getMessage());
                break;
            }
            $rel->inspected = true;
            $rel->save();
        }
        $nntp->close();
        return self::SUCCESS;
    }

    private function inspect(Nntp $nntp, Release $rel, ReleaseBuilder $builder): void
    {
        $files = $rel->files;
        $names = array_keys($files);

        // 1) Echten Namen aus der PAR2-Indexdatei lesen
        if ($rel->obfuscated) {
            $par = $this->pick($files, '/\.par2$/i', '/\.vol\d+\+\d+\.par2$/i');
            $bin = $par ? $this->firstSegment($nntp, $files[$par]) : null;
            $found = $bin ? Par2::fileNames($bin) : [];
            if ($found) {
                $real = Par2::releaseName($found);
                if ($real) {
                    $rel->name = mb_substr($real, 0, 500);
                    $rel->obfuscated = Obfuscation::looks($real);
                }
                $names = array_merge($names, array_column($found, 'name'));
                $this->line("{$rel->id}: Name aufgelöst -> {$rel->name}");
            }
        }

        // 2) RAR-Header auf Verschlüsselung prüfen
        $rar = $this->pick($files, '/\.rar$/i', '/\.part(?!0*1\.rar)\d+\.rar$/i');
        if ($rar && $rel->status === 'ok') {
            $bin = $this->firstSegment($nntp, $files[$rar], 512);
            if ($bin !== null && RarInspector::isEncrypted($bin) === true) {
                $rel->status = 'passworded';
                $this->line("{$rel->id}: passwortgeschützt");
            }
        }

        // 3) Mit den echten Dateinamen neu einordnen
        $rel->category_id = \App\Services\Categorizer::detect($rel->name, $names);
        if (!$rel->locked && $rel->status === 'ok') {
            $rel->status = Classifier::status($rel->name, $names, (int) $rel->size, \App\Models\Blacklist::matches($rel->poster, $rel->obfuscated ? null : $rel->name), (int) config('indexer.min_size'), (array) config('indexer.spam_words'));
        }
    }

    /** Wählt die Datei mit passender Endung; $exclude-Treffer nur, wenn nichts anderes da ist. */
    private function pick(array $files, string $include, string $exclude): ?string
    {
        $best = null;
        foreach ($files as $name => $f) {
            if (!preg_match($include, $name) || preg_match($exclude, $name)) {
                continue;
            }
            if ($best === null || $f['total'] < $files[$best]['total']) {
                $best = $name;
            }
        }
        if ($best === null) {
            foreach ($files as $name => $f) {
                if (preg_match($include, $name)) {
                    return $name;
                }
            }
        }
        return $best;
    }

    private function firstSegment(Nntp $nntp, array $file, ?int $limit = null): ?string
    {
        $segs = $file['segs'];
        $first = $segs[min(array_keys($segs))];
        $lines = $nntp->body($first['id']);
        return $lines === null ? null : Yenc::decode($lines, $limit);
    }
}
