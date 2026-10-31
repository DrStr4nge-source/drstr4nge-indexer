<?php
namespace App\Services;

/** Liest echte Dateinamen aus einer PAR2-Datei (FileDesc-Pakete). Reine PHP-Klasse. */
class Par2
{
    private const MAGIC = "PAR2\0PKT";
    private const FILEDESC = "PAR 2.0\0FileDesc";
    private const NOISE = ['par2', 'nfo', 'sfv', 'nzb', 'txt', 'jpg', 'jpeg', 'png', 'url', 'srr', 'srs'];

    /** @return array<int,array{name:string,length:int}> */
    public static function fileNames(string $bin): array
    {
        $found = [];
        $pos = 0;
        $total = strlen($bin);
        while ($pos + 64 <= $total) {
            if (substr($bin, $pos, 8) !== self::MAGIC) {
                $next = strpos($bin, self::MAGIC, $pos + 1);
                if ($next === false) {
                    break;
                }
                $pos = $next;
                continue;
            }
            $len = unpack('P', substr($bin, $pos + 8, 8))[1];
            if ($len < 64 || $len % 4 !== 0 || $pos + $len > $total) {
                break; // abgeschnittenes oder kaputtes Paket
            }
            if (substr($bin, $pos + 48, 16) === self::FILEDESC && $len > 120) {
                $body = substr($bin, $pos + 64, $len - 64);
                $size = unpack('P', substr($body, 48, 8))[1];
                $name = rtrim(substr($body, 56), "\0");
                if ($name !== '') {
                    $found[] = ['name' => $name, 'length' => (int) $size];
                }
            }
            $pos += $len;
        }
        return $found;
    }

    /** Release-Name = Name der größten Nutzdatei ohne Endung und .partNN. */
    public static function releaseName(array $entries): ?string
    {
        $best = null;
        foreach ($entries as $e) {
            $ext = strtolower(pathinfo($e['name'], PATHINFO_EXTENSION));
            if (in_array($ext, self::NOISE, true)) {
                continue;
            }
            if ($best === null || $e['length'] > $best['length']) {
                $best = $e;
            }
        }
        if (!$best) {
            return null;
        }
        $stem = pathinfo($best['name'], PATHINFO_FILENAME);
        $stem = preg_replace('/\.(part\d+|r\d{2,3}|vol\d+\+\d+)$/i', '', $stem);
        return $stem !== '' ? $stem : null;
    }
}
