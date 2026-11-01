<?php

namespace App\Support;

/**
 * Auslastung des Servers (Linux): CPU, RAM, Swap, Speicherplatz, Last, Laufzeit.
 * Liest /proc direkt, braucht keine Zusatzprogramme. Fehlt /proc, sind die Werte null.
 */
class SystemStats
{
    public static function collect(string $diskPath = '/'): array
    {
        // CPU: Unterschied zur letzten Messung (Admin-Seite fragt alle 5 s). Nur ohne frischen Vorwert kurz warten.
        $b = self::read('/proc/stat');
        $prev = self::cache('get');
        if ($prev && time() - $prev['t'] <= 60) {
            $a = $prev['stat'];
        } else {
            $a = $b;
            usleep(100000);
            $b = self::read('/proc/stat');
        }
        if ($b) {
            self::cache('put', ['t' => time(), 'stat' => $b]);
        }
        $mem = self::parseMeminfo(self::read('/proc/meminfo'));
        $load = self::parseLoadavg(self::read('/proc/loadavg'));
        $uptime = self::read('/proc/uptime');
        $total = @disk_total_space($diskPath) ?: null;
        $free = @disk_free_space($diskPath);

        return [
            'cpu' => ($a && $b) ? self::cpuPercent(self::parseCpu($a), self::parseCpu($b)) : null,
            'cores' => $a ? max(1, preg_match_all('/^cpu\d+ /m', $a)) : null,
            'load' => $load,
            'ram' => $mem ? self::usage($mem['MemTotal'] ?? 0, ($mem['MemTotal'] ?? 0) - ($mem['MemAvailable'] ?? $mem['MemFree'] ?? 0)) : null,
            'swap' => ($mem && ($mem['SwapTotal'] ?? 0) > 0) ? self::usage($mem['SwapTotal'], $mem['SwapTotal'] - ($mem['SwapFree'] ?? 0)) : null,
            'disk' => $total ? self::usage((int) $total, (int) $total - (int) $free) : null,
            'uptime' => $uptime ? (int) (float) strtok($uptime, ' ') : null,
        ];
    }

    /** Erste Zeile "cpu ..." aus /proc/stat: [busy, total] */
    public static function parseCpu(string $stat): ?array
    {
        if (! preg_match('/^cpu\s+([\d\s]+)$/m', $stat, $m)) {
            return null;
        }
        $v = array_map('intval', preg_split('/\s+/', trim($m[1])));
        $idle = ($v[3] ?? 0) + ($v[4] ?? 0); // idle + iowait
        $total = array_sum(array_slice($v, 0, 8)); // guest ist schon in user enthalten
        return [$total - $idle, $total];
    }

    public static function cpuPercent(?array $a, ?array $b): ?float
    {
        if (! $a || ! $b || $b[1] <= $a[1]) {
            return null;
        }
        return round(max(0, min(100, ($b[0] - $a[0]) / ($b[1] - $a[1]) * 100)), 1);
    }

    /** /proc/meminfo in Bytes */
    public static function parseMeminfo(?string $text): ?array
    {
        if (! $text || ! preg_match_all('/^(\w+):\s+(\d+)\s*kB/m', $text, $m, PREG_SET_ORDER)) {
            return null;
        }
        $out = [];
        foreach ($m as $row) {
            $out[$row[1]] = (int) $row[2] * 1024;
        }
        return $out;
    }

    public static function parseLoadavg(?string $text): ?array
    {
        if (! $text) {
            return null;
        }
        $p = explode(' ', trim($text));
        return count($p) >= 3 ? [(float) $p[0], (float) $p[1], (float) $p[2]] : null;
    }

    public static function usage(int $total, int $used): ?array
    {
        if ($total <= 0) {
            return null;
        }
        $used = max(0, min($total, $used));
        return ['total' => $total, 'used' => $used, 'percent' => round($used / $total * 100, 1)];
    }

    /** Vorwert im Laravel-Cache (fehlt Laravel, z.B. in Tests: ohne Vorwert) */
    private static function cache(string $op, ?array $v = null): ?array
    {
        if (!class_exists(\Illuminate\Support\Facades\Cache::class) || !\Illuminate\Support\Facades\Cache::getFacadeRoot()) {
            return null;
        }
        try {
            if ($op === 'get') {
                return \Illuminate\Support\Facades\Cache::get('system.cpu');
            }
            \Illuminate\Support\Facades\Cache::put('system.cpu', $v, 120);
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function read(string $file): ?string
    {
        $c = @file_get_contents($file);
        return $c === false ? null : $c;
    }
}
