<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Sperren gegen doppelten Crawl: eine für den Gesamtlauf, eine je Gruppe.
 * Dazu ein Fortschritts-Eintrag je laufender Gruppe für indexer:status.
 */
class CrawlLock
{
    public const TTL = 7200; // nur noch für den Fortschritts-Eintrag

    /**
     * Sperre für den Gesamtlauf. Sie gilt, solange der Prozess lebt, der sie hält (nicht nach einer festen Zeit),
     * damit auch ein stundenlanger Backfill nicht doppelt läuft. Stirbt der Prozess, ist sie beim nächsten Versuch frei.
     * @return object|null Sperre (mit release()) oder null, wenn ein lebender Crawl sie hält
     */
    public static function all(): ?object
    {
        return self::acquire('indexer-crawl-all');
    }

    public static function group(int $id): ?object
    {
        return self::acquire("indexer-crawl-group-$id");
    }

    private static function acquire(string $key): ?object
    {
        $me = ['pid' => getmypid(), 'since' => time()];
        for ($try = 0; $try < 2; $try++) {
            if (Cache::add($key, $me, 7 * 86400)) { // add ist atomar: nur einer bekommt die Sperre
                return new class($key, $me['pid']) {
                    public function __construct(private string $key, private int $pid) {}
                    public function release(): void
                    {
                        if ((Cache::get($this->key)['pid'] ?? null) === $this->pid) {
                            Cache::forget($this->key);
                        }
                    }
                };
            }
            $cur = Cache::get($key);
            if ($cur && self::alive((int) ($cur['pid'] ?? 0))) {
                return null; // läuft wirklich noch
            }
            Cache::forget($key); // Prozess tot (Absturz, kill, Neustart): Sperre übernehmen
        }
        return null;
    }

    /** Lebt ein Crawl-Prozess mit dieser PID? (prüft auch, dass die PID nicht inzwischen ein anderes Programm ist) */
    public static function alive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }
        if (is_readable("/proc/$pid/cmdline")) {
            return str_contains((string) @file_get_contents("/proc/$pid/cmdline"), 'indexer:crawl');
        }
        return function_exists('posix_kill') ? @posix_kill($pid, 0) : false;
    }

    /** Wer hält gerade die Sperre? (für indexer:status) */
    public static function holder(): ?array
    {
        $cur = Cache::get('indexer-crawl-all');
        return $cur && self::alive((int) $cur['pid']) ? $cur : null;
    }

    public static function forceRelease(): void
    {
        Cache::forget('indexer-crawl-all');
        foreach (\App\Models\Newsgroup::pluck('id') as $id) {
            Cache::forget("indexer-crawl-group-$id");
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
            if (($p = Cache::get("crawl.progress.$id")) && self::alive((int) ($p['pid'] ?? 0))) {
                $out[$id] = $p;
            }
        }
        return $out;
    }
}
