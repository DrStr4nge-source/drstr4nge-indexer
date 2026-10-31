<?php
namespace App\Support;

/**
 * Kleiner QR-Code-Erzeuger (Byte-Modus, Fehlerkorrektur M, Version 1-10, bis 213 Byte) mit SVG-Ausgabe.
 * Für die Einrichtung der Zwei-Faktor-Anmeldung, damit der geheime Schlüssel den Server nicht verlässt.
 */
class QrCode
{
    // Version => [Codewörter gesamt, Fehlerkorrektur je Block, Blöcke] (Stufe M)
    private const VERSIONS = [
        1 => [26, 10, 1], 2 => [44, 16, 1], 3 => [70, 26, 1], 4 => [100, 18, 2], 5 => [134, 24, 2],
        6 => [172, 16, 4], 7 => [196, 18, 4], 8 => [242, 22, 4], 9 => [292, 22, 5], 10 => [346, 26, 5],
    ];
    private const ALIGN = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34],
        7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];

    private int $size;
    private array $m = [];      // [y][x] => bool
    private array $fn = [];     // Funktionsmodule

    /** @return array<int,array<int,bool>> Matrix [y][x] */
    public static function matrix(string $text): array
    {
        return (new self)->encode($text);
    }

    public static function svg(string $text, int $scale = 6): string
    {
        $mx = self::matrix($text);
        $n = count($mx);
        $q = 4; // Ruhezone
        $path = '';
        foreach ($mx as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M' . ($x + $q) . ',' . ($y + $q) . 'h1v1h-1z';
                }
            }
        }
        $w = ($n + 2 * $q);
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $w . '" width="' . ($w * $scale) . '" height="' . ($w * $scale)
            . '" shape-rendering="crispEdges" role="img" aria-label="QR-Code"><rect width="100%" height="100%" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
    }

    private function encode(string $text): array
    {
        $len = strlen($text);
        foreach (self::VERSIONS as $ver => [$total, $ecLen, $blocks]) {
            $dataCw = $total - $ecLen * $blocks;
            $ccBits = $ver < 10 ? 8 : 16;
            if (4 + $ccBits + 8 * $len <= $dataCw * 8) {
                break;
            }
            if ($ver === 10) {
                throw new \InvalidArgumentException('Text zu lang für QR-Code');
            }
        }
        // Bits: Modus 0100 (Byte), Länge, Daten, Abschluss, Füllbytes
        $bits = [];
        $put = function (int $v, int $n) use (&$bits) { for ($i = $n - 1; $i >= 0; $i--) $bits[] = ($v >> $i) & 1; };
        $put(4, 4);
        $put($len, $ccBits);
        for ($i = 0; $i < $len; $i++) $put(ord($text[$i]), 8);
        $cap = $dataCw * 8;
        $put(0, min(4, $cap - count($bits)));
        $put(0, (8 - count($bits) % 8) % 8);
        for ($pad = 0xEC; count($bits) < $cap; $pad ^= 0xEC ^ 0x11) $put($pad, 8);
        $data = [];
        foreach (array_chunk($bits, 8) as $b) $data[] = bindec(implode('', $b));

        $this->size = $ver * 4 + 17;
        $this->m = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->fn = $this->m;
        $this->functionPatterns($ver);
        $this->placeCodewords($this->interleave($data, $total, $ecLen, $blocks));

        // Maske mit der geringsten Strafpunktzahl
        $best = null;
        $bestScore = PHP_INT_MAX;
        $base = $this->m;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->m = $base;
            $this->applyMask($mask);
            $this->formatBits($mask);
            $s = $this->penalty();
            if ($s < $bestScore) {
                $bestScore = $s;
                $best = $this->m;
            }
        }
        return $best;
    }

    private function set(int $x, int $y, bool $dark): void
    {
        $this->m[$y][$x] = $dark;
        $this->fn[$y][$x] = true;
    }

    private function functionPatterns(int $ver): void
    {
        $n = $this->size;
        for ($i = 0; $i < $n; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$n - 4, 3], [3, $n - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $x = $cx + $dx; $y = $cy + $dy;
                    if ($x >= 0 && $x < $n && $y >= 0 && $y < $n) {
                        $d = max(abs($dx), abs($dy));
                        $this->set($x, $y, $d !== 2 && $d !== 4);
                    }
                }
            }
        }
        $al = self::ALIGN[$ver];
        $k = count($al);
        foreach ($al as $i => $ax) {
            foreach ($al as $j => $ay) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $k - 1) || ($i === $k - 1 && $j === 0)) continue;
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->set($ax + $dx, $ay + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->formatBits(0); // reserviert
        if ($ver >= 7) {
            $rem = $ver;
            for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            $v = $ver << 12 | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = (($v >> $i) & 1) === 1;
                $a = $n - 11 + $i % 3; $b = intdiv($i, 3);
                $this->set($a, $b, $bit);
                $this->set($b, $a, $bit);
            }
        }
    }

    private function formatBits(int $mask): void
    {
        $d = (0 << 3) | $mask; // Stufe M = 00
        $rem = $d;
        for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        $bits = (($d << 10) | $rem) ^ 0x5412;
        $b = fn ($i) => (($bits >> $i) & 1) === 1;
        $n = $this->size;
        for ($i = 0; $i <= 5; $i++) $this->set(8, $i, $b($i));
        $this->set(8, 7, $b(6));
        $this->set(8, 8, $b(7));
        $this->set(7, 8, $b(8));
        for ($i = 9; $i < 15; $i++) $this->set(14 - $i, 8, $b($i));
        for ($i = 0; $i < 8; $i++) $this->set($n - 1 - $i, 8, $b($i));
        for ($i = 8; $i < 15; $i++) $this->set(8, $n - 15 + $i, $b($i));
        $this->set(8, $n - 8, true);
    }

    private function interleave(array $data, int $total, int $ecLen, int $numBlocks): array
    {
        $short = $numBlocks - $total % $numBlocks;
        $shortLen = intdiv($total, $numBlocks);
        $div = self::rsDivisor($ecLen);
        $blocks = [];
        $k = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $n = $shortLen - $ecLen + ($i < $short ? 0 : 1);
            $dat = array_slice($data, $k, $n);
            $k += $n;
            $ecc = self::rsRemainder($dat, $div);
            if ($i < $short) $dat[] = null;
            $blocks[] = array_merge($dat, $ecc);
        }
        $out = [];
        for ($i = 0, $l = count($blocks[0]); $i < $l; $i++) {
            foreach ($blocks as $j => $blk) {
                if ($i !== $shortLen - $ecLen || $j >= $short) $out[] = $blk[$i];
            }
        }
        return $out;
    }

    private function placeCodewords(array $cw): void
    {
        $n = $this->size;
        $i = 0;
        $total = count($cw) * 8;
        for ($right = $n - 1; $right >= 1; $right -= 2) {
            if ($right === 6) $right = 5;
            for ($vert = 0; $vert < $n; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $up = (($right + 1) & 2) === 0;
                    $y = $up ? $n - 1 - $vert : $vert;
                    if (!$this->fn[$y][$x] && $i < $total) {
                        $this->m[$y][$x] = (($cw[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        $n = $this->size;
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($this->fn[$y][$x]) continue;
                $inv = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y % 2 + $x * $y % 3) === 0,
                    6 => (($x * $y % 2 + $x * $y % 3) % 2) === 0,
                    7 => ((($x + $y) % 2 + $x * $y % 3) % 2) === 0,
                };
                if ($inv) $this->m[$y][$x] = !$this->m[$y][$x];
            }
        }
    }

    /** Vereinfachte Strafpunkte (Reihen gleicher Farbe, 2x2-Blöcke, Hell/Dunkel-Verhältnis) */
    private function penalty(): int
    {
        $n = $this->size;
        $s = 0;
        $dark = 0;
        for ($a = 0; $a < $n; $a++) {
            foreach ([0, 1] as $dir) {
                $run = 1;
                for ($b = 1; $b < $n; $b++) {
                    $cur = $dir ? $this->m[$b][$a] : $this->m[$a][$b];
                    $prev = $dir ? $this->m[$b - 1][$a] : $this->m[$a][$b - 1];
                    if ($cur === $prev) {
                        $run++;
                        if ($run === 5) $s += 3; elseif ($run > 5) $s++;
                    } else {
                        $run = 1;
                    }
                }
            }
        }
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if ($this->m[$y][$x]) $dark++;
                if ($x < $n - 1 && $y < $n - 1) {
                    $c = $this->m[$y][$x];
                    if ($c === $this->m[$y][$x + 1] && $c === $this->m[$y + 1][$x] && $c === $this->m[$y + 1][$x + 1]) $s += 3;
                }
            }
        }
        $s += intdiv(abs($dark * 20 - $n * $n * 10), $n * $n) * 10;
        return $s;
    }

    private static function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z & 0xFF;
    }

    private static function rsDivisor(int $deg): array
    {
        $r = array_fill(0, $deg, 0);
        $r[$deg - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $deg; $i++) {
            for ($j = 0; $j < $deg; $j++) {
                $r[$j] = self::gfMul($r[$j], $root);
                if ($j + 1 < $deg) $r[$j] ^= $r[$j + 1];
            }
            $root = self::gfMul($root, 0x02);
        }
        return $r;
    }

    private static function rsRemainder(array $data, array $div): array
    {
        $r = array_fill(0, count($div), 0);
        foreach ($data as $b) {
            $f = $b ^ array_shift($r);
            $r[] = 0;
            foreach ($div as $i => $d) $r[$i] ^= self::gfMul($d, $f);
        }
        return $r;
    }
}
