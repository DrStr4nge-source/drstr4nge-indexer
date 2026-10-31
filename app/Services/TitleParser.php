<?php
namespace App\Services;

/** Holt Titel, Jahr, Staffel und Folge aus einem Release-Namen. Reine PHP-Klasse. */
class TitleParser
{
    private const NOISE = ['german', 'english', 'french', 'multi', 'dl', 'dual', 'dubbed', 'ld', 'md', 'ac3', 'proper', 'repack',
        'internal', 'extended', 'uncut', 'unrated', 'remastered', 'dc', 'complete', 'limited', 'hdr', 'hdr10', 'truefrench', 'subbed'];
    private const QUALITY = '/(?<![a-z0-9])(480p|576p|720p|1080p|1080i|2160p|4k|uhd|bluray|blu-ray|bdrip|brrip|web-?dl|webrip|web|dvdrip|dvdscr|hdrip|hdtv|x264|x265|h\.?26[45]|hevc|xvid|remux)(?![a-z0-9])/i';

    /** @return array{type:string,title:string,year:?int,season:?int,episode:?int}|null */
    public static function parse(string $name): ?array
    {
        $name = trim($name);

        // Anime: "[Gruppe] Titel - 05 (1080p)"
        if (preg_match('/^\[[^\]]+\]\s*(.+?)\s-\s(\d{1,3})(?:v\d)?\s*(?:[\(\[]|$)/', $name, $m)) {
            $title = self::clean($m[1]);
            return $title === '' ? null : ['type' => 'tv', 'title' => $title, 'year' => null, 'season' => null, 'episode' => (int) $m[2]];
        }

        $s = preg_replace('/^\[[^\]]*\]\s*/', '', $name);
        $s = str_replace(['.', '_'], ' ', $s);

        // Serie: S01E02, 1x02, S01 (Staffelpaket) oder Datum
        $tv = '/(?<![a-z0-9])(?:s(\d{1,2})[ -]?e(\d{1,3})|(\d{1,2})x(\d{2,3})|s(\d{1,2})|(?:19|20)\d{2} \d{2} \d{2})(?![a-z0-9])/i';
        if (preg_match($tv, $s, $m, PREG_OFFSET_CAPTURE)) {
            $title = substr($s, 0, $m[0][1]);
            $year = null;
            if (preg_match('/^(.*?)\s*(?<![a-z0-9])((?:19|20)\d{2})\s*$/', $title, $y) && trim($y[1]) !== '') {
                $title = $y[1];
                $year = (int) $y[2];
            }
            $title = self::clean($title);
            if ($title === '') {
                return null;
            }
            $season = isset($m[1]) && $m[1][1] >= 0 ? (int) $m[1][0] : (isset($m[3]) && $m[3][1] >= 0 ? (int) $m[3][0] : (isset($m[5]) && $m[5][1] >= 0 ? (int) $m[5][0] : null));
            $episode = isset($m[2]) && $m[2][1] >= 0 ? (int) $m[2][0] : (isset($m[4]) && $m[4][1] >= 0 ? (int) $m[4][0] : null);
            return ['type' => 'tv', 'title' => $title, 'year' => $year, 'season' => $season, 'episode' => $episode];
        }

        // Film: letztes Jahr vor dem ersten Qualitäts-Token
        $limit = preg_match(self::QUALITY, $s, $q, PREG_OFFSET_CAPTURE) ? $q[0][1] : strlen($s);
        if (!preg_match_all('/(?<![a-z0-9])((?:19|20)\d{2})(?![a-z0-9])/', $s, $ys, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $pick = null;
        foreach ($ys[1] as [$y, $pos]) {
            if ($pos < $limit && $pos > 0) {
                $pick = [(int) $y, $pos];
            }
        }
        if (!$pick) {
            return null;
        }
        $title = self::clean(substr($s, 0, $pick[1]));
        return $title === '' ? null : ['type' => 'movie', 'title' => $title, 'year' => $pick[0], 'season' => null, 'episode' => null];
    }

    /** Normalisiert für Vergleiche: klein, nur Buchstaben und Ziffern. */
    public static function normalize(string $s): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s));
    }

    /** Ähnlichkeit zweier Titel in Prozent (0-100). */
    public static function similarity(string $a, string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === '' || $b === '') {
            return 0.0;
        }
        similar_text($a, $b, $pct);
        return $pct;
    }

    private static function clean(string $t): string
    {
        $t = preg_replace('/[\[\(][^\]\)]*[\]\)]/', ' ', $t);
        $t = trim(preg_replace('/\s+/', ' ', str_replace(['.', '_'], ' ', $t)), " -");
        $words = explode(' ', $t);
        while ($words && in_array(strtolower(end($words)), self::NOISE, true)) {
            array_pop($words);
        }
        return trim(implode(' ', $words), " -");
    }
}
