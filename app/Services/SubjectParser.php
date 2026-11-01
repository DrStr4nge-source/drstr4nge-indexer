<?php
namespace App\Services;

/**
 * Zerlegt Usenet-Betreffzeilen. Reine PHP-Klasse.
 * Versteht u.a.:
 *   Name [01/20] - "datei.part01.rar" yEnc (1/50)
 *   (7/8) - Description - "Name.rar.vol12+10.PAR2" - 318,06 MB - yEnc (4/11)
 *   [01/20] - "Name.S01E01.mkv" (1/50)
 *   1 yEnc (78/1421)
 */
class SubjectParser
{
    /** Betreff endet auf eine typische Dateiendung */
    private const FILE_END = '/\.(vol\d+[+-]\d+\.par2|par2|part\d+\.rar|rar|r\d{2,3}|7z|zip|\d{3}|nfo|sfv|nzb|mkv|mp4|avi|m4v|ts|m2ts|iso|mp3|flac|epub|pdf)$/i';

    /** Wörter, die manche Uploader statt eines Namens in den Betreff schreiben */
    private const GENERIC = ['description', 'desc', 'yenc', 'upload', 'uploads', 'post', 'posts', 'file', 'files', 'datei', 'dateien',
        'rar', 'par2', 'nzb', 'misc', 'new', 'test', 'part', 'sample', 'archive', 'archiv', 'backup'];

    /** Ungültiges UTF-8 (meist Latin-1/Windows-1252) in UTF-8 umwandeln, damit die Datenbank es speichern kann. */
    public static function utf8(string $s): string
    {
        if (mb_check_encoding($s, 'UTF-8')) {
            return $s;
        }
        return mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
    }

    /**
     * @return array{file:string, part:int, parts:int, expected:int, prefixName:string, fileBase:?string}|null
     */
    public static function parse(string $subject): ?array
    {
        $s = trim(self::utf8($subject));

        // Teilezähler: bevorzugt der nach "yEnc", sonst der letzte (n/m) im Betreff
        if (!preg_match_all('/(yEnc\s*)?\((\d{1,6})\/(\d{1,6})\)/i', $s, $all, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $pick = null;
        foreach ($all as $m) {
            if ($m[1][0] !== '' && $m[1][1] >= 0) {
                $pick = $m; // mit yEnc davor
            }
        }
        $pick ??= end($all);
        $part = (int) $pick[2][0];
        $parts = (int) $pick[3][0];
        if ($parts < 1 || $part < 1 || $part > $parts) {
            return null;
        }
        $head = substr($s, 0, $pick[0][1]);

        // Dateiname: letzter Text in Anführungszeichen vor dem Zähler
        $file = null;
        $prefix = $head;
        if (preg_match_all('/"([^"]+)"/', $head, $q, PREG_OFFSET_CAPTURE)) {
            $last = end($q[1]);
            $file = trim($last[0]);
            $prefix = substr($head, 0, $last[1] - 1);
        }

        $expected = preg_match('/[\[\(]\s*\d+\s*\/\s*(\d+)\s*[\]\)]/', $prefix, $e) ? min(5000, (int) $e[1]) : 0;
        $prefixName = self::cleanPrefix($prefix);
        $fileBase = $file !== null && $file !== '' ? self::stripExtensions($file) : null;

        // Dateiname ohne Anführungszeichen ("Name - GRP.vol001+002.par2 yEnc (1/3)"): Endung abschneiden,
        // damit alle Dateien eines Posts ein Release bilden und nicht jede Datei ein eigenes
        if ($file === null && $prefixName !== '' && preg_match(self::FILE_END, $prefixName)) {
            $base = self::stripExtensions($prefixName);
            if ($base !== '' && $base !== $prefixName) {
                $file = $prefixName;
                $fileBase = $base;
                $prefixName = self::sceneName($base);
            }
        }

        return [
            'file' => $file ?: ($prefixName !== '' ? $prefixName : 'datei'),
            'part' => $part,
            'parts' => $parts,
            'expected' => $expected,
            'prefixName' => $prefixName,
            'fileBase' => $fileBase,
        ];
    }

    /** "Name.rar.vol12+10.PAR2" => "Name", "Name.part01.rar" => "Name", "Show.S01E01.mkv" => "Show.S01E01" */
    public static function stripExtensions(string $file): string
    {
        $rx = [
            '/\.vol\d+[+-]\d+\.par2$/i', '/\.par2$/i', '/\.part\d+\.rar$/i', '/\.(rar|r\d{2,3}|s\d{2})$/i',
            '/\.(7z|zip|tar|gz)$/i', '/\.\d{3}$/', '/\.(nfo|sfv|nzb|srr|srs|txt|jpg|jpeg|png|url|md5|sha1)$/i',
            '/\.(mkv|mp4|avi|m4v|ts|m2ts|wmv|mov|iso|img|bin|exe|epub|mobi|pdf|cbr|cbz|mp3|flac|m4a|m4b)$/i',
        ];
        $prev = null;
        while ($prev !== $file) {
            $prev = $file;
            foreach ($rx as $r) {
                $file = preg_replace($r, '', $file);
            }
        }
        return trim($file, " .-_");
    }

    /** Prüft, ob ein Text als Release-Name taugt (nicht leer, keine Platzhalter, nicht verschleiert). */
    public static function meaningful(?string $name): bool
    {
        if ($name === null) {
            return false;
        }
        $n = trim($name);
        if (mb_strlen($n) < 3 || ctype_digit(str_replace([' ', '.', '-', '_'], '', $n))) {
            return false;
        }
        if (in_array(mb_strtolower($n), self::GENERIC, true)) {
            return false;
        }
        return !Obfuscation::looks($n);
    }

    /** "Name.x264 - GRP" => "Name.x264-GRP" (Szene-Schreibweise, damit Pre-Daten passen) */
    public static function sceneName(string $n): string
    {
        return preg_match('/^(\S+)\s+-\s+([A-Za-z0-9]+)$/', $n, $m) ? $m[1] . '-' . $m[2] : $n;
    }

    private static function cleanPrefix(string $p): string
    {
        $p = preg_replace('/[\[\(]\s*\d+\s*\/\s*\d+\s*[\]\)]/', ' ', $p);          // [01/20] (7/8)
        $p = preg_replace('/\b\d+(?:[.,]\d+)?\s*[KMGT]i?B\b/i', ' ', $p);         // 318,06 MB
        $p = preg_replace('/\byEnc\b/i', ' ', $p);
        $p = trim(preg_replace('/\s+/', ' ', $p));
        // Platzhalter-Wörter am Anfang/Ende entfernen ("Description - Name" => "Name")
        $words = preg_split('/\s*[-|]\s*/', $p, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_values(array_filter($words, fn ($w) => !in_array(mb_strtolower(trim($w)), self::GENERIC, true)));
        return trim(implode(' - ', $words), " -_|\t");
    }
}
