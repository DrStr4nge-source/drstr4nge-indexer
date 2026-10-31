<?php
namespace App\Services;

/** Erkennt die Newznab-Kategorie aus Release-Name und Dateiendungen. Reine PHP-Klasse. */
class Categorizer
{
    private const NOT = '(?<![a-z0-9])';
    private const END = '(?![a-z0-9])';

    private const AUDIO_LOSSLESS = ['flac', 'ape', 'wv'];
    private const AUDIO = ['mp3', 'm4a', 'ogg', 'aac', 'wav', 'opus', 'wma'];
    private const BOOKS = ['epub', 'mobi', 'azw3', 'azw', 'fb2', 'lit', 'djvu', 'pdf'];
    private const COMICS = ['cbr', 'cbz'];
    private const IGNORE = ['nfo', 'par2', 'sfv', 'nzb', 'txt', 'jpg', 'jpeg', 'png', 'url', 'srr', 'srs'];

    public static function detect(string $name, array $files = []): int
    {
        $exts = [];
        foreach ($files as $f) {
            $e = strtolower(pathinfo((string) $f, PATHINFO_EXTENSION));
            if ($e !== '' && !in_array($e, self::IGNORE, true)) {
                $exts[$e] = true;
            }
        }
        $exts = array_keys($exts);

        // XXX
        if (preg_match('/' . self::NOT . 'xxx' . self::END . '/i', $name)) {
            return 6000;
        }

        // Dateiendungen sind das zuverlässigste Signal für Audio und Bücher
        if ($exts) {
            if (!array_diff($exts, array_merge(self::AUDIO, self::AUDIO_LOSSLESS, ['m4b']))) {
                if (preg_match('/audiobook/i', $name) || in_array('m4b', $exts, true)) {
                    return 3030;
                }
                return array_intersect($exts, self::AUDIO_LOSSLESS) ? 3040 : (in_array('mp3', $exts, true) ? 3010 : 3000);
            }
            if (!array_diff($exts, self::COMICS)) {
                return 7030;
            }
            if (!array_diff($exts, array_merge(self::BOOKS, self::COMICS))) {
                return 7020;
            }
        }

        $uhd = (bool) preg_match('/2160p|' . self::NOT . '4k' . self::END . '|' . self::NOT . 'uhd' . self::END . '/i', $name);
        $hd = (bool) preg_match('/' . self::NOT . '(720|1080)[pi]' . self::END . '/i', $name);

        // Anime
        if (preg_match('/anime|\[(horriblesubs|subsplease|erai-raws|judas|ember|asw)\]/i', $name)) {
            return 5070;
        }

        // TV
        $tv = '/' . self::NOT . 's\d{1,2}[ ._-]?e\d{1,3}' . self::END
            . '|' . self::NOT . '\d{1,2}x\d{2,3}' . self::END
            . '|' . self::NOT . 'season[ ._]?\d{1,2}' . self::END
            . '|' . self::NOT . 's\d{1,2}' . self::END
            . '|' . self::NOT . '(19|20)\d{2}[ ._-]\d{2}[ ._-]\d{2}' . self::END . '/i';
        if (preg_match($tv, $name)) {
            if (preg_match('/' . self::NOT . '(ufc|wwe|nfl|nba|nhl|mlb|formula[ .]?1|f1)' . self::END . '/i', $name)) {
                return 5060;
            }
            return $uhd ? 5045 : ($hd ? 5040 : 5030);
        }

        // Filme: Jahr + Qualitäts-Token
        $quality = '/(720p|1080p|1080i|2160p|bluray|blu-ray|bdrip|brrip|web-?dl|webrip|dvdrip|dvdscr|hdrip|hdtv|x264|x265|h\.?26[45]|hevc|xvid|divx|remux|hdcam|telesync)|'
            . self::NOT . 'cam' . self::END . '/i';
        if (preg_match('/' . self::NOT . '(19|20)\d{2}' . self::END . '/', $name) && preg_match($quality, $name)) {
            if ($uhd) {
                return 2045;
            }
            if (preg_match('/blu-?ray/i', $name) && preg_match('/complete|untouched/i', $name)) {
                return 2050;
            }
            return $hd ? 2040 : 2030;
        }

        // Konsolen
        $console = [
            'ps3' => 1080, 'ps4' => 1180, 'xbox360' => 1050, 'xbox' => 1040, 'wii' => 1030,
            'ps5' => 1000, 'nsw' => 1000, 'switch' => 1000, 'wiiu' => 1030, 'xboxone' => 1040,
        ];
        foreach ($console as $tok => $id) {
            if (preg_match('/' . self::NOT . $tok . self::END . '/i', $name)) {
                return $id;
            }
        }

        // PC-Spiele
        if (preg_match('/' . self::NOT . '(codex|skidrow|plaza|reloaded|fitgirl|dodi|gog|razor1911|flt|tenoke|empress)' . self::END . '/i', $name)) {
            return 4050;
        }

        // PC allgemein
        if (preg_match('/' . self::NOT . '(macos|osx)' . self::END . '/i', $name)) {
            return 4030;
        }
        if (preg_match('/' . self::NOT . 'iso' . self::END . '/i', $name)) {
            return 4020;
        }
        if (preg_match('/keygen|cracked|' . self::NOT . 'crack' . self::END . '|' . self::NOT . 'patch' . self::END . '|portable|activat|adobe|photoshop|' . self::NOT . '(x64|x86)' . self::END . '/i', $name)) {
            return 4010;
        }

        return 7000;
    }
}
