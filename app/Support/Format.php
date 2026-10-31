<?php
namespace App\Support;

class Format
{
    /** 1536 => "1,5 KB"; deutsche Schreibweise */
    public static function bytes(int|float|null $b): string
    {
        $b = (float) $b;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($b >= 1024 && $i < count($units) - 1) {
            $b /= 1024;
            $i++;
        }
        return number_format($b, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
    }
}
