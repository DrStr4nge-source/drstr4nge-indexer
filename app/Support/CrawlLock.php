<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Sperren gegen doppelten Crawl: eine für den Gesamtlauf, eine je Gruppe.
 * Dazu ein Fortschritts-Eintrag je laufender Gruppe für indexer:status.
 */
class CrawlLock
{
    public const TTL = 7200; // Sekunden; stürzt ein Crawl ab, ist die Sperre spätestens danach frei

    /** @return object|null Sperre oder null, wenn schon belegt */
    public static function all(): ?object
    {
        $l = Cache::lock('indexer-crawl-all', self::TTL);
        return $l->get() ? $l : null;
    }

    public static function group(int $id): ?object
    {
        $l = Cache::lock("indexer-crawl-group-$id", self::TTL);
        return $l->get() ? $l : null;
    }

    public static function forceRelease(): void
    {
        Cache::lock('indexer-crawl-all')->forceRelease();
        foreach (\App\Models\Newsgroup::pluck('id') as $id) {
            Cache::lock("indexer-crawl-group-$id")->forceRelease();
            Cache::forget("crawl.progress.$id");
        }
    }

    public static function progress(int $id, string $name, int $at, int $high): void
    {
        $old = Cache::get("crawl.progress.$id");
        Cache::put("crawl.progress.$id", [
            'name' => $name, 'at' => $at, 'high' => $high, 'pid' => getmypid(),
            'started' => $old['started'] ?? time(), 'updated' => time(),
        ], self::TTL);
    }

    public static function done(int $id): void
    {
        Cache::forget("crawl.progress.$id");
    }

    /** Laufende Gruppen: [id => progress] */
    public static function running(iterable $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if ($p = Cache::get("crawl.progress.$id")) {
                $out[$id] = $p;
            }
        }
        return $out;
    }
}
