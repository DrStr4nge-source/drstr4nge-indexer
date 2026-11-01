<?php
namespace App\Services\Search;

use App\Models\Release;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Eine Stelle für alle Release-Suchen (Webseite und API).
 * Mit Suchbegriff und aktivem Manticore: Volltextsuche dort. Sonst, oder wenn Manticore nicht antwortet: MySQL.
 */
class ReleaseSearch
{
    /**
     * @param int[] $categories bereits aufgelöste Kategorie-IDs
     * @param callable|null $extra zusätzliche MySQL-Filter (z.B. IMDb-ID); erzwingt die MySQL-Suche
     * @return array{rows:Collection,total:int,engine:string}
     */
    public static function run(?string $q, array $categories, ?int $maxAgeDays, int $offset, int $limit, ?callable $extra = null): array
    {
        $q = trim((string) $q);
        if ($q !== '' && $extra === null && ReleaseIndex::enabled()) {
            try {
                $after = $maxAgeDays !== null ? now()->subDays($maxAgeDays)->getTimestamp() : null;
                $res = ReleaseIndex::client()->search($q, $categories, $after, $offset, $limit);
                if ($res !== null) {
                    $order = array_flip($res['ids']);
                    $rows = Release::lite()->visible()->with('media')->whereIn('id', $res['ids'] ?: [0])->get()
                        ->sortBy(fn ($r) => $order[$r->id] ?? PHP_INT_MAX)->values();
                    return ['rows' => $rows, 'total' => $res['total'], 'engine' => 'manticore'];
                }
            } catch (\Throwable $e) {
                Log::warning('Manticore-Suche fehlgeschlagen, nutze MySQL: ' . $e->getMessage());
            }
        }

        $query = Release::lite()->visible()->with('media')->search($q)->category($categories);
        if ($maxAgeDays !== null) {
            $query->where('posted_at', '>=', now()->subDays($maxAgeDays));
        }
        if ($extra) {
            $extra($query);
        }
        // Zählen kostet bei Hunderttausenden Treffern viel Zeit: höchstens 10.000 zählen (Seiten reichen trotzdem weit)
        $total = \Illuminate\Support\Facades\DB::query()->fromSub((clone $query)->toBase()->select('releases.id')->limit(10000), 'treffer')->count();
        $rows = $query->orderByDesc('posted_at')->orderByDesc('id')->offset($offset)->limit($limit)->get();
        return ['rows' => $rows, 'total' => $total, 'engine' => 'mysql'];
    }
}
