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
        // Nur die IDs holen; jedes Release einzeln mit Artikel-Liste laden (spart Speicher bei großen Releases)
        $ids = Release::where('inspected', false)
            ->where('completion', '>=', config('indexer.min_completion'))
            ->orderBy('id')->limit($limit)->pluck('id');
        if ($ids->isEmpty()) {
            $this->info('Nichts zu prüfen.');
            return self::SUCCESS;
        }

        try {
            $nntp->connect();
        } catch (\RuntimeException $e) {
            // z.B. alle Verbindungen beim Crawlen belegt: beim nächsten Lauf erneut, kein Fehler im Zeitplan
            $this->warn('Keine Verbindung zum Usenet-Server: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::warning('Prüfen (PAR2/RAR): keine Verbindung: ' . $e->getMessage());
            return self::SUCCESS;
        }
        $done = $skipped = 0;
        foreach ($ids as $id) {
            $rel = Release::find($id);
            if (!$rel || $rel->inspected) {
                continue;
            }
            try {
                $this->inspect($nntp, $rel, $builder);
            } catch (\App\Services\NntpConnectionLost $e) {
                // Verbindung weg: einmal neu verbinden, Release beim nächsten Lauf erneut prüfen
                $this->warn($e->getMessage() . ', verbinde neu');
                try {
                    $nntp->close();
                    $nntp->connect();
                    continue;
                } catch (\RuntimeException $e2) {
                    \Illuminate\Support\Facades\Log::warning('Prüfen (PAR2/RAR): Verbindung verloren: ' . $e2->getMessage());
                    break;
                }
            } catch (\Throwable $e) {
                // Kaputte PAR2/RAR-Daten oder Ähnliches bei genau diesem Release: überspringen statt alles abzubrechen
                $skipped++;
                \Illuminate\Support\Facades\Log::warning(sprintf('Prüfen: Release %d "%s" übersprungen: %s', $rel->id, mb_substr((string) $rel->name, 0, 80), mb_substr($e->getMessage(), 0, 200)));
            }
            $rel->inspected = true;
            try {
                $rel->save();
                $done++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Prüfen: Release {$rel->id} nicht gespeichert: " . mb_substr($e->getMessage(), 0, 200));
                Release::whereKey($rel->id)->update(['inspected' => true]); // nicht endlos erneut versuchen
            }
        }
        $nntp->close();
        $this->info("$done Releases geprüft" . ($skipped ? ", $skipped übersprungen (siehe Log)" : '') . '.');
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
                // Echte Größe und Anzahl der Nutzdateien merken (für den Abgleich mit der PreDB)
                $data = array_filter($found, fn ($e) => !preg_match('/\.par2$/i', $e['name']));
                if ($data) {
                    $rel->real_size = array_sum(array_column($data, 'length'));
                    $rel->real_files = count($data);
                }
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
