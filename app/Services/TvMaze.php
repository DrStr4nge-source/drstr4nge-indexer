<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * TVmaze (https://www.tvmaze.com/api, kostenlos, ohne Key): ergänzt bei Serien TVDB-, IMDb- und TVmaze-ID.
 * Damit finden Sonarr & Co. Folgen zuverlässiger. Daten unter CC BY-SA, Quelle wird im Admin genannt.
 */
class TvMaze
{
    private const BASE = 'https://api.tvmaze.com';

    /** @return array|null ['tvmaze_id','tvdb_id','imdb_id','year','title'] */
    public static function find(?int $tvdb, ?string $imdb, string $title, ?int $year): ?array
    {
        if ($tvdb && ($s = self::get('/lookup/shows', ['thetvdb' => $tvdb]))) {
            return self::map($s);
        }
        if ($imdb && ($s = self::get('/lookup/shows', ['imdb' => $imdb]))) {
            return self::map($s);
        }
        $s = self::get('/singlesearch/shows', ['q' => $title]);
        return $s && self::sameShow($s, $title, $year) ? self::map($s) : null;
    }

    public static function map(array $s): array
    {
        $ext = $s['externals'] ?? [];
        return [
            'tvmaze_id' => (int) ($s['id'] ?? 0) ?: null,
            'tvdb_id' => isset($ext['thetvdb']) ? (int) $ext['thetvdb'] : null,
            'imdb_id' => !empty($ext['imdb']) && preg_match('/^tt\d+$/', $ext['imdb']) ? $ext['imdb'] : null,
            'year' => !empty($s['premiered']) ? (int) substr($s['premiered'], 0, 4) : null,
            'title' => $s['name'] ?? null,
        ];
    }

    /** Suche nach Namen: nur übernehmen, wenn Titel sehr ähnlich und Startjahr passt */
    public static function sameShow(array $s, string $title, ?int $year): bool
    {
        $norm = fn ($t) => preg_replace('/[^a-z0-9]+/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', (string) $t) ?: (string) $t));
        similar_text($norm($s['name'] ?? ''), $norm($title), $pct);
        $y = !empty($s['premiered']) ? (int) substr($s['premiered'], 0, 4) : null;
        return $pct >= 85 && (!$year || !$y || abs($y - $year) <= 1);
    }

    private static function get(string $path, array $q): ?array
    {
        $res = Http::timeout(15)->withUserAgent('DrStr4nge-Indexer/' . config('indexer.version'))->acceptJson()->get(self::BASE . $path, $q);
        if ($res->status() === 404) {
            return null;
        }
        if ($res->status() === 429) {
            throw new \RuntimeException('TVmaze: zu viele Anfragen, später weiter');
        }
        if (!$res->successful()) {
            throw new \RuntimeException('TVmaze antwortet mit HTTP ' . $res->status());
        }
        $j = $res->json();
        return is_array($j) ? $j : null;
    }
}
