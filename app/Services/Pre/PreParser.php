<?php
namespace App\Services\Pre;

use App\Models\Predb;

/**
 * Liest Pre-Ansagen im Format der Kanäle #PreNNTmux und #nZEDbPRE (synIRC), z.B.:
 * NEW: [DT: 2026-10-31 14:00:00] [TT: Some.Movie.2026.German.1080p.WEB.h264-GRP] [SC: srrdb] [CT: X264] [RQ: N/A] [SZ: 4.37GB] [FL: 47] [FN: N/A]
 * UPD: ... gleiche Felder, ergänzt Größe/Dateien
 * NUK: ... [NUKED: dupe.2026-10-30]
 */
class PreParser
{
    private const PATTERN = '/^(?<type>NEW|UPD|NUK):\s*\[DT: (?<time>[^\]]+)\]\s*\[TT: (?<title>[^\]]+)\]\s*\[SC: (?<source>[^\]]+)\]\s*\[CT: (?<category>[^\]]+)\]'
        . '\s*\[RQ: (?<req>[^\]]+)\]\s*\[SZ: (?<size>[^\]]+)\]\s*\[FL: (?<files>[^\]]+)\]\s*(?:\[FN: (?<filename>[^\]]+)\]\s*)?'
        . '(?:\[(?<nuke>(?:UN|MOD|RE|OLD)?NUKED?): (?<reason>[^\]]*)\])?\s*$/i';

    /** @return array|null Felder für die Tabelle predb, null wenn die Zeile keine Pre-Ansage ist */
    public static function parse(string $line): ?array
    {
        $line = trim(self::stripFormatting($line));
        if (!preg_match(self::PATTERN, $line, $m)) {
            return null;
        }
        $na = fn (?string $v) => ($v === null || $v === '' || strtoupper(trim($v)) === 'N/A') ? null : trim($v);
        $title = trim($m['title']);
        if ($title === '' || mb_strlen($title) > 255 || preg_match('/\s/', $title)) {
            return null; // Szenenamen enthalten keine Leerzeichen
        }
        $ts = strtotime(trim($m['time']) . ' UTC');
        $files = $na($m['files'] ?? null);

        $pre = [
            'type' => strtoupper($m['type']),
            'title' => $title,
            'source' => $na($m['source']),
            'category' => ($c = $na($m['category'])) ? mb_substr($c, 0, 50) : null,
            'size' => self::bytes($na($m['size'])),
            'files' => ($files !== null && preg_match('/^\d+/', $files, $f)) ? (int) $f[0] : null,
            'filename' => ($fn = $na($m['filename'] ?? null)) ? mb_substr($fn, 0, 255) : null,
            'predate' => $ts ? gmdate('Y-m-d H:i:s', $ts) : null,
            'nuked' => null,
            'nuke_reason' => null,
        ];
        if (!empty($m['nuke'])) {
            $pre['nuked'] = match (strtoupper(rtrim($m['nuke'], 'Dd'))) {
                'UNNUKE' => Predb::UNNUKED, 'MODNUKE' => Predb::MODNUKED, 'RENUKE' => Predb::RENUKED,
                'OLDNUKE' => Predb::OLDNUKE, default => Predb::NUKED,
            };
            $pre['nuke_reason'] = mb_substr(trim($m['reason'] ?? ''), 0, 255) ?: null;
        }
        return $pre;
    }

    /** "4.37GB" => Bytes; unbekannt => null */
    public static function bytes(?string $s): ?int
    {
        if ($s === null || !preg_match('/^([\d.,]+)\s*([KMGT]?)i?B?$/i', trim($s), $m)) {
            return null;
        }
        $n = (float) str_replace(',', '.', $m[1]);
        $exp = ['' => 0, 'K' => 1, 'M' => 2, 'G' => 3, 'T' => 4][strtoupper($m[2])];
        return (int) round($n * (1024 ** $exp));
    }

    /** IRC-Farben, Fett, Unterstrichen usw. entfernen */
    public static function stripFormatting(string $s): string
    {
        $s = preg_replace('/\x03(\d{1,2}(,\d{1,2})?)?/', '', $s);
        return preg_replace('/[\x02\x0F\x16\x1D\x1F\x11\x1E]/', '', $s);
    }
}
