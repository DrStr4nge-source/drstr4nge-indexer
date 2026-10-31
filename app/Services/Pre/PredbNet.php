<?php
namespace App\Services\Pre;

use App\Models\Predb;
use Illuminate\Support\Facades\Http;

/**
 * PreDB über die API von predb.net (https://api.predb.net).
 * ?limit=N&page=P = neueste Einträge, ?q=Name = Suche. Größe kommt in MB, pretime als Unix-Zeit.
 */
class PredbNet
{
    /** @return array<int,array> Einträge im Format von PreParser::parse() */
    public static function latest(int $page = 1, int $limit = 100): array
    {
        return self::request(['limit' => $limit, 'page' => $page]);
    }

    public static function search(string $name): array
    {
        return self::request(['q' => $name]);
    }

    private static function request(array $query): array
    {
        $res = Http::timeout(20)->retry(2, 2000, throw: false)
            ->withUserAgent('DrStr4nge-Indexer/' . config('indexer.version'))
            ->acceptJson()
            ->get(rtrim((string) config('indexer.predb_api.url'), '/') . '/', $query);
        if ($res->status() === 429) {
            throw new \RuntimeException('predb.net: zu viele Anfragen (429), später erneut');
        }
        if (!$res->successful()) {
            throw new \RuntimeException('predb.net antwortet mit HTTP ' . $res->status());
        }
        $json = $res->json();
        if (!is_array($json)) {
            throw new \RuntimeException('predb.net: Antwort ist kein JSON');
        }
        return self::rows($json);
    }

    /** Antwort (mit oder ohne "data"-Hülle) in Pre-Felder umwandeln; unbrauchbare Zeilen fallen weg. */
    public static function rows(array $json): array
    {
        $list = isset($json['data']) && is_array($json['data']) ? $json['data'] : (array_is_list($json) ? $json : []);
        $out = [];
        foreach ($list as $r) {
            if (is_array($r) && ($p = self::map($r))) {
                $out[] = $p;
            }
        }
        return $out;
    }

    public static function map(array $r): ?array
    {
        $title = trim((string) ($r['release'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255 || preg_match('/\s/', $title)) {
            return null;
        }
        $time = (int) ($r['pretime'] ?? 0);
        $mb = (float) ($r['size'] ?? 0);
        $status = (int) ($r['status'] ?? 0);
        $reason = trim((string) ($r['reason'] ?? ''));
        $section = trim((string) ($r['section'] ?? ''));
        $files = (int) ($r['files'] ?? 0);
        return [
            'type' => 'API',
            'title' => $title,
            'source' => 'predb.net',
            'category' => $section !== '' ? mb_substr($section, 0, 50) : null,
            'size' => $mb > 0 ? (int) round($mb * 1024 * 1024) : null,
            'files' => $files > 0 ? $files : null,
            'filename' => null,
            'predate' => $time > 0 ? gmdate('Y-m-d H:i:s', $time) : null,
            'nuked' => $status !== 0 ? Predb::NUKED : null,
            'nuke_reason' => $status !== 0 && $reason !== '' ? mb_substr($reason, 0, 255) : null,
        ];
    }
}
