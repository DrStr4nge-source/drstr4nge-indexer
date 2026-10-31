<?php
namespace App\Services;

/** yEnc-Decoder. $limit = nach so vielen Ausgabe-Bytes aufhören (spart Zeit). Reine PHP-Klasse. */
class Yenc
{
    public static function decode(array $lines, ?int $limit = null): string
    {
        $out = '';
        foreach ($lines as $line) {
            if (str_starts_with($line, '=ybegin') || str_starts_with($line, '=ypart') || str_starts_with($line, '=yend')) {
                continue;
            }
            $line = rtrim($line, "\r\n");
            $len = strlen($line);
            for ($i = 0; $i < $len; $i++) {
                $c = ord($line[$i]);
                if ($c === 61) { // '=' Escape
                    $i++;
                    if ($i >= $len) {
                        break;
                    }
                    $c = ord($line[$i]) - 64;
                }
                $out .= chr(($c - 42) & 255);
            }
            if ($limit !== null && strlen($out) >= $limit) {
                return substr($out, 0, $limit);
            }
        }
        return $out;
    }
}
