<?php
namespace App\Services;

/**
 * Prüft die ersten Bytes einer RAR-Datei auf Verschlüsselung.
 * true = Passwort nötig, false = nein, null = unbekannt (kein RAR / zu kurz).
 */
class RarInspector
{
    public static function isEncrypted(string $b): ?bool
    {
        if (str_starts_with($b, "Rar!\x1A\x07\x00")) {
            return self::rar4($b);
        }
        if (str_starts_with($b, "Rar!\x1A\x07\x01\x00")) {
            return self::rar5($b);
        }
        return null;
    }

    private static function rar4(string $b): ?bool
    {
        if (strlen($b) < 20 || ord($b[9]) !== 0x73) {
            return null;
        }
        $flags = unpack('v', substr($b, 10, 2))[1];
        if ($flags & 0x0080) {
            return true; // Header verschlüsselt
        }
        $size = unpack('v', substr($b, 12, 2))[1];
        $next = 7 + $size;
        if (strlen($b) < $next + 7 || ord($b[$next + 2]) !== 0x74) {
            return null;
        }
        $fflags = unpack('v', substr($b, $next + 3, 2))[1];
        return (bool) ($fflags & 0x0004);
    }

    private static function vint(string $b, int &$pos): ?int
    {
        $v = 0;
        for ($shift = 0; $shift < 63; $shift += 7) {
            if ($pos >= strlen($b)) {
                return null;
            }
            $byte = ord($b[$pos++]);
            $v |= ($byte & 0x7F) << $shift;
            if (!($byte & 0x80)) {
                return $v;
            }
        }
        return null;
    }

    private static function rar5(string $b): ?bool
    {
        $pos = 8;
        for ($i = 0; $i < 4; $i++) {
            $pos += 4; // CRC32
            $hsize = self::vint($b, $pos);
            if ($hsize === null) {
                return null;
            }
            $end = $pos + $hsize;
            $type = self::vint($b, $pos);
            $flags = self::vint($b, $pos);
            if ($type === null || $flags === null) {
                return null;
            }
            if ($type === 4) {
                return true; // Verschlüsselungs-Header
            }
            $extra = ($flags & 0x01) ? (self::vint($b, $pos) ?? 0) : 0;
            $data = ($flags & 0x02) ? (self::vint($b, $pos) ?? 0) : 0;
            if ($type === 2) { // Datei-Header: Extra-Bereich nach Verschlüsselungs-Record absuchen
                if ($end > strlen($b)) {
                    return null;
                }
                $p = $end - $extra;
                while ($extra > 0 && $p < $end) {
                    $rsize = self::vint($b, $p);
                    if ($rsize === null || $rsize === 0) {
                        break;
                    }
                    $recEnd = $p + $rsize;
                    if (self::vint($b, $p) === 1) {
                        return true;
                    }
                    $p = $recEnd;
                }
                return false;
            }
            $pos = $end + $data;
        }
        return null;
    }
}
