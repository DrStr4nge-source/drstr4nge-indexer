<?php
namespace App\Services\Pre;

use App\Models\Predb;
use App\Models\Release;

/** Speichert Pre-Ansagen und verknüpft passende Releases. */
class PreStore
{
    /** @return array{0:Predb,1:bool} [Pre, neu angelegt?] */
    public static function save(array $pre): array
    {
        $row = Predb::where('title', $pre['title'])->first();
        $fields = self::merge($row?->only(['category', 'source', 'size', 'files', 'filename', 'predate', 'nuked', 'nuke_reason']), $pre);
        if ($row) {
            if ($fields) {
                $row->update($fields);
            }
        } else {
            $row = Predb::create(['title' => $pre['title']] + $fields);
            // Releases mit genau diesem Namen, die schon vor dem Pre gefunden wurden
            Release::where('name', $pre['title'])->whereNull('pre_id')->update(['pre_id' => $row->id]);
        }
        return [$row, $row->wasRecentlyCreated];
    }

    /**
     * Welche Felder geschrieben werden. Leere Werte überschreiben nie vorhandene,
     * die erste Pre-Zeit bleibt (UPD/NUK kommen oft mit späterer Zeit).
     */
    public static function merge(?array $old, array $new): array
    {
        $out = [];
        foreach (['category', 'source', 'size', 'files', 'filename'] as $k) {
            if (($new[$k] ?? null) !== null && ($old[$k] ?? null) != $new[$k]) {
                $out[$k] = $new[$k];
            }
        }
        if (($new['predate'] ?? null) && empty($old['predate'])) {
            $out['predate'] = $new['predate'];
        }
        if (($new['nuked'] ?? null) !== null) {
            $out['nuked'] = $new['nuked'];
            $out['nuke_reason'] = $new['nuke_reason'] ?? null;
        }
        return $out;
    }
}
