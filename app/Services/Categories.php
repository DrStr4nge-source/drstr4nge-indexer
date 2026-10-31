<?php
namespace App\Services;

class Categories
{
    public static function name(int $id): string
    {
        return config("indexer.categories.$id.0", 'Other');
    }

    public static function parent(int $id): int
    {
        return (int) (config("indexer.categories.$id.1") ?? $id);
    }

    /** Eltern-IDs (z.B. 5000) werden zu allen Unterkategorien aufgelöst. */
    public static function expand(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            $out[] = $id;
            foreach (config('indexer.categories') as $cid => [$n, $p]) {
                if ($p === $id) {
                    $out[] = $cid;
                }
            }
        }
        return array_values(array_unique($out));
    }
}
