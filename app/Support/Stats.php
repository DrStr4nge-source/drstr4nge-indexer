<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;

/** Teure Zählungen kurz zwischenspeichern (bei Millionen Zeilen dauert COUNT(*) spürbar) */
class Stats
{
    public static function remember(string $key, callable $fn, int $seconds = 300): mixed
    {
        return Cache::remember("stats.$key", $seconds, $fn);
    }
}
