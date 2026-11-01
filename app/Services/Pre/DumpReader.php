<?php
namespace App\Services\Pre;

use App\Models\Predb;

/**
 * Liest PreDB-Dumps zeilenweise:
 * - nZEDb-Format (github.com/nZEDb/nZEDbPre_Dumps): 12 Felder, getrennt durch zwei Tabs, Werte in '...', \N = leer:
 *   title, nfo, size, files, filename, nuked, nukereason, category, predate, source, requestid, groupname
 * - CSV mit Kopfzeile (Komma, Semikolon oder Tab), Spalten z.B. title/release/name, predate/pretime/time, size, files, category/section, nuked, reason
 */
class DumpReader
{
    // nZEDb: 0 kein, 1 unnuked, 2 nuked, 3 modnuke, 4 renuked, 5 oldnuke
    private const NZEDB_NUKE = [1 => Predb::UNNUKED, 2 => Predb::NUKED, 3 => Predb::MODNUKED, 4 => Predb::RENUKED, 5 => Predb::OLDNUKE];

    /** @return \Generator<array> Einträge im Format von PreParser::parse() */
    public static function read($handle): \Generator
    {
        $header = null;
        $sep = null;
        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') continue;
            if ($header === null && $sep === null) {
                if (substr_count($line, "\t\t") >= 8) {
                    $sep = 'nzedb';
                } else {
                    $sep = substr_count($line, ';') > substr_count($line, ',') ? ';' : (str_contains($line, "\t") ? "\t" : ',');
                    $header = array_map(fn ($h) => strtolower(trim($h, " \"'")), str_getcsv($line, $sep));
                    continue;
                }
            }
            $row = $sep === 'nzedb' ? self::nzedb($line) : self::csv($line, $sep, $header);
            if ($row) yield $row;
        }
    }

    public static function nzedb(string $line): ?array
    {
        $f = array_map([self::class, 'unquote'], explode("\t\t", $line));
        if (count($f) < 9) return null;
        [$title, , $size, $files, $filename, $nuked, $reason, $category, $predate] = $f;
        return self::make($title, $predate, $size, $files, $category, $f[9] ?? null,
            self::NZEDB_NUKE[(int) $nuked] ?? null, $reason, $filename);
    }

    public static function csv(string $line, string $sep, array $header): ?array
    {
        $v = str_getcsv($line, $sep);
        if (count($v) < 2) return null;
        // Kurze Zeilen bekommen leere Felder, überzählige Felder (z.B. Komma im Grund ohne Anführungszeichen) fallen weg
        $v = array_slice(array_pad($v, count($header), null), 0, count($header));
        $r = array_combine($header, $v);
        $get = function (array $keys) use ($r) { foreach ($keys as $k) if (isset($r[$k]) && $r[$k] !== '') return $r[$k]; return null; };
        $nuked = $get(['nuked', 'nuke', 'status']);
        return self::make($get(['title', 'release', 'name', 'rlsname']), $get(['predate', 'pretime', 'time', 'date', 'created']), $get(['size']),
            $get(['files']), $get(['category', 'section', 'cat']), $get(['source']), $nuked && $nuked !== '0' ? Predb::NUKED : null,
            $get(['nukereason', 'reason', 'nuke_reason']), null);
    }

    private static function make(?string $title, ?string $predate, ?string $size, ?string $files, ?string $category, ?string $source, ?int $nuked, ?string $reason, ?string $filename): ?array
    {
        $title = trim((string) $title);
        if (mb_strlen($title) <= 8 || mb_strlen($title) > 255 || preg_match('/\s/', $title)) return null;
        $ts = $predate === null ? false : (ctype_digit($predate) ? (int) $predate : strtotime($predate . ' UTC'));
        $bytes = $size === null ? null : (ctype_digit($size) ? (int) $size * 1024 * 1024 : PreParser::bytes($size)); // reine Zahl = MB
        return [
            'title' => $title,
            'category' => $category ? mb_substr($category, 0, 50) : null,
            'source' => $source ? mb_substr($source, 0, 50) : null,
            'size' => $bytes ?: null,
            'files' => $files !== null && ctype_digit($files) && (int) $files > 0 ? (int) $files : null,
            'filename' => $filename ? mb_substr($filename, 0, 255) : null,
            'predate' => $ts && $ts > 0 ? gmdate('Y-m-d H:i:s', $ts) : null,
            'nuked' => $nuked ?? Predb::NONE,
            'nuke_reason' => $nuked && $reason ? mb_substr($reason, 0, 255) : null,
        ];
    }

    private static function unquote(string $v): ?string
    {
        if ($v === '\N' || $v === 'NULL') return null;
        if (strlen($v) >= 2 && $v[0] === "'" && substr($v, -1) === "'") {
            $v = stripcslashes(substr($v, 1, -1)); // MySQL-Escape (\' \\ \t)
        }
        return $v === '' ? null : $v;
    }
}
