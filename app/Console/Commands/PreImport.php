<?php
namespace App\Console\Commands;

use App\Services\Pre\DumpReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * PreDB-Dumps einspielen: eine Datei (.csv/.csv.gz), ein Ordner mit Dumps, eine URL
 * oder mit --nzedb alle Tages-Dumps von github.com/nZEDb/nZEDbPre_Dumps (2014 bis Juni 2024).
 * Vorhandene Einträge bleiben unverändert, neue kommen dazu. Danach werden Releases verknüpft.
 */
class PreImport extends Command
{
    protected $signature = 'indexer:pre-import
        {quelle? : Datei, Ordner oder URL}
        {--nzedb : alle Dumps von GitHub (nZEDbPre_Dumps) laden}
        {--since= : nur Pres ab diesem Datum, z.B. 2022-01-01}
        {--no-match : danach keine Releases verknüpfen}';
    protected $description = 'PreDB-Dumps importieren (nZEDb-Format oder CSV)';

    private const REPO = 'nZEDb/nZEDbPre_Dumps';
    private int $new = 0;
    private int $seen = 0;

    public function handle(): int
    {
        $since = $this->option('since') ? strtotime($this->option('since') . ' UTC') : null;
        if ($this->option('since') && !$since) {
            $this->error('Datum nicht verstanden, Beispiel: --since=2022-01-01');
            return self::FAILURE;
        }
        $src = $this->argument('quelle');
        if ($this->option('nzedb')) {
            $ok = $this->fromGithub($since);
        } elseif ($src && is_dir($src)) {
            $files = glob(rtrim($src, '/') . '/{,*/}*_predb_dump.csv*', GLOB_BRACE) ?: glob(rtrim($src, '/') . '/*.{csv,gz}', GLOB_BRACE);
            sort($files);
            foreach ($files as $f) {
                if ($since && preg_match('/(\d{9,})_predb_dump/', $f, $m) && (int) $m[1] < $since) continue;
                $this->importFile($f, $since);
            }
            $ok = (bool) $files;
        } elseif ($src && is_file($src)) {
            $ok = $this->importFile($src, $since);
        } elseif ($src && preg_match('#^https?://#', $src)) {
            $ok = $this->importUrl($src, $since);
        } else {
            $this->error('Quelle angeben (Datei, Ordner, URL) oder --nzedb.');
            return self::FAILURE;
        }
        $this->info(sprintf('Fertig: %s Einträge gelesen, %s neu in der PreDB.', number_format($this->seen, 0, ',', '.'), number_format($this->new, 0, ',', '.')));
        if (!$this->option('no-match') && $this->new > 0) {
            $this->call('indexer:pre-match');
        }
        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function fromGithub(?int $since): bool
    {
        $api = fn (string $path) => Http::timeout(30)->withUserAgent('DrStr4nge-Indexer')->acceptJson()
            ->get('https://api.github.com/repos/' . self::REPO . '/contents/' . $path);
        try {
            return $this->githubList($api, $since);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->error('GitHub nicht erreichbar: ' . $e->getMessage());
            return false;
        }
    }

    private function githubList(callable $api, ?int $since): bool
    {
        $root = $api('dumps');
        if (!$root->successful()) {
            $this->error('GitHub antwortet mit HTTP ' . $root->status() . ($root->status() === 403 ? ' (Abfrage-Limit, in einer Stunde erneut)' : ''));
            return false;
        }
        $list = [];
        foreach ($root->json() as $dir) {
            if (($dir['type'] ?? '') !== 'dir') continue;
            $sub = $api($dir['path']);
            if (!$sub->successful()) {
                $this->error("GitHub: {$dir['path']} nicht lesbar (HTTP {$sub->status()})");
                return false;
            }
            foreach ($sub->json() as $f) {
                if (preg_match('/^(\d+)_predb_dump\.csv\.gz$/', $f['name'] ?? '', $m) && (!$since || (int) $m[1] >= $since)) {
                    $list[(int) $m[1]] = $f['download_url'];
                }
            }
        }
        ksort($list);
        $this->info(count($list) . ' Dumps zu laden' . ($since ? ' (ab ' . gmdate('d.m.Y', $since) . ')' : '') . '. Das dauert eine Weile.');
        $i = 0;
        foreach ($list as $url) {
            $i++;
            if (!$this->importUrl($url, $since, sprintf('[%d/%d] ', $i, count($list)))) {
                $this->warn("Übersprungen: $url");
            }
        }
        return true;
    }

    private function importUrl(string $url, ?int $since, string $prefix = ''): bool
    {
        $base = tempnam(sys_get_temp_dir(), 'predb');
        $tmp = $base . (str_ends_with($url, '.gz') ? '.gz' : '');
        if ($tmp !== $base) {
            @unlink($base); // tempnam legt die Datei schon an; wir nutzen den Namen mit .gz
        }
        try {
            $res = Http::timeout(120)->retry(3, 3000, throw: false)->withUserAgent('DrStr4nge-Indexer')->sink($tmp)->get($url);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            @unlink($tmp);
            $this->error($prefix . basename((string) parse_url($url, PHP_URL_PATH)) . ': nicht erreichbar (' . $e->getMessage() . ')');
            return false;
        }
        if (!$res->successful()) {
            @unlink($tmp);
            $this->error($prefix . basename(parse_url($url, PHP_URL_PATH)) . ': HTTP ' . $res->status());
            return false;
        }
        try {
            return $this->importFile($tmp, $since, $prefix . basename(parse_url($url, PHP_URL_PATH)));
        } finally {
            @unlink($tmp);
        }
    }

    private function importFile(string $file, ?int $since, ?string $label = null): bool
    {
        $gz = str_ends_with($file, '.gz') || bin2hex((string) @file_get_contents($file, false, null, 0, 2)) === '1f8b';
        $h = $gz ? @gzopen($file, 'rb') : @fopen($file, 'rb');
        if (!$h) {
            $this->error("Nicht lesbar: $file");
            return false;
        }
        $before = $this->new;
        $batch = [];
        $now = now()->toDateTimeString();
        foreach (DumpReader::read($h) as $row) {
            $this->seen++;
            if ($since && $row['predate'] && strtotime($row['predate'] . ' UTC') < $since) continue;
            $batch[] = $row + ['created_at' => $now, 'updated_at' => $now];
            if (count($batch) >= 1000) {
                $this->new += DB::table('predb')->insertOrIgnore($batch);
                $batch = [];
            }
        }
        if ($batch) {
            $this->new += DB::table('predb')->insertOrIgnore($batch);
        }
        $gz ? gzclose($h) : fclose($h);
        $this->line(sprintf('%s: %s neu', $label ?? basename($file), number_format($this->new - $before, 0, ',', '.')));
        return true;
    }
}
