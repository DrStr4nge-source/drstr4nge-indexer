<?php
namespace App\Services\Search;

use App\Models\Release;
use Illuminate\Support\Facades\Log;

/** Hält den Manticore-Index synchron mit der Release-Tabelle. Fehler stoppen nie den Crawler. */
class ReleaseIndex
{
    private static ?Manticore $client = null;

    public static function enabled(): bool
    {
        return (bool) config('indexer.manticore.enabled');
    }

    public static function client(): Manticore
    {
        return self::$client ??= Manticore::fromConfig();
    }

    /** Für Tests: eigenen Client setzen (oder null zum Zurücksetzen). */
    public static function useClient(?Manticore $client): void
    {
        self::$client = $client;
    }

    /** Darf ein Release mit diesen Werten in der Suche erscheinen? (gleiche Regeln wie Release::visible) */
    public static function indexable(array $a): bool
    {
        return (int) ($a['completion'] ?? 0) >= (int) config('indexer.min_completion')
            && ($a['status'] ?? 'ok') === 'ok'
            && !($a['obfuscated'] ?? false);
    }

    public static function row(Release $r): array
    {
        return [
            'id' => (int) $r->id,
            'name' => (string) $r->name,
            'category_id' => (int) $r->category_id,
            'posted_at' => (int) ($r->posted_at?->getTimestamp() ?? 0),
            'size' => (int) $r->size,
        ];
    }

    /** Wird nach jedem Speichern eines Releases aufgerufen. */
    public static function sync(Release $r): void
    {
        if (!self::enabled()) {
            return;
        }
        $now = self::indexable($r->getAttributes());
        $before = !$r->wasRecentlyCreated && self::indexable($r->getOriginal());
        try {
            if ($now && (!$before || $r->wasChanged(['name', 'category_id', 'posted_at', 'size']))) {
                self::client()->upsert([self::row($r)]);
            } elseif ($before && !$now) {
                self::client()->delete([$r->id]);
            }
        } catch (\Throwable $e) {
            Log::warning('Manticore-Sync fehlgeschlagen (später indexer:search-index ausführen): ' . $e->getMessage());
        }
    }

    /** @param int[] $ids */
    public static function remove(array $ids): void
    {
        if (!self::enabled() || !$ids) {
            return;
        }
        try {
            self::client()->delete($ids);
        } catch (\Throwable $e) {
            Log::warning('Manticore-Löschen fehlgeschlagen: ' . $e->getMessage());
        }
    }
}
