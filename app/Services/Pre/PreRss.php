<?php
namespace App\Services\Pre;

use Illuminate\Support\Facades\Http;

/** Pre-Daten aus RSS-Feeds (z.B. https://predb.me/?rss=1): Titel = Releasename, pubDate = Pre-Zeit. */
class PreRss
{
    public static function fetch(string $url): array
    {
        $res = Http::timeout(20)->retry(2, 2000, throw: false)
            ->withUserAgent('DrStr4nge-Indexer/' . config('indexer.version'))->get($url);
        if (!$res->successful()) {
            throw new \RuntimeException(parse_url($url, PHP_URL_HOST) . ' antwortet mit HTTP ' . $res->status());
        }
        return self::parse($res->body(), (string) parse_url($url, PHP_URL_HOST));
    }

    public static function parse(string $xml, string $source = 'rss'): array
    {
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_use_internal_errors($prev);
        if (!$doc) {
            throw new \RuntimeException("$source: Feed ist kein gültiges XML");
        }
        $items = $doc->channel->item ?? $doc->item ?? [];
        $out = [];
        foreach ($items as $it) {
            $title = trim(html_entity_decode((string) $it->title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($title === '' || mb_strlen($title) > 255 || preg_match('/\s/', $title)) {
                continue;
            }
            $ts = strtotime((string) $it->pubDate);
            $cat = trim((string) ($it->category ?? ''));
            $out[] = [
                'type' => 'RSS', 'title' => $title, 'source' => mb_substr($source, 0, 50),
                'category' => $cat !== '' ? mb_substr($cat, 0, 50) : null,
                'size' => null, 'files' => null, 'filename' => null,
                'predate' => $ts ? gmdate('Y-m-d H:i:s', $ts) : null,
                'nuked' => null, 'nuke_reason' => null,
            ];
        }
        return $out;
    }
}
