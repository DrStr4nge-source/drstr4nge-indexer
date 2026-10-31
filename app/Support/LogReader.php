<?php
namespace App\Support;

/** Liest die letzten Einträge aus storage/logs/laravel*.log (Laravel-Format "[Zeit] umgebung.STUFE: Text"). */
class LogReader
{
    public const LEVELS = ['DEBUG' => 0, 'INFO' => 1, 'NOTICE' => 2, 'WARNING' => 3, 'ERROR' => 4, 'CRITICAL' => 5, 'ALERT' => 6, 'EMERGENCY' => 7];

    /** @return string[] Log-Dateien, neueste zuerst */
    public static function files(string $dir): array
    {
        $f = glob(rtrim($dir, '/') . '/laravel*.log') ?: [];
        usort($f, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        return $f;
    }

    /**
     * @return array<int,array{time:string,level:string,message:string,details:string}> neueste zuerst
     */
    public static function entries(array $files, int $minLevel = 3, int $limit = 300, int $maxBytes = 4 * 1024 * 1024, string $search = ''): array
    {
        $out = [];
        foreach ($files as $file) {
            $out = array_merge($out, self::parse(self::tail($file, $maxBytes), $minLevel, $search));
            if (count($out) >= $limit) break;
        }
        return array_slice($out, 0, $limit);
    }

    public static function parse(string $text, int $minLevel = 3, string $search = ''): array
    {
        $entries = [];
        $cur = null;
        foreach (preg_split('/\r?\n/', $text) as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\] [\w-]+\.([A-Z]+): (.*)$/', $line, $m)) {
                if ($cur) $entries[] = $cur;
                $cur = ['time' => $m[1], 'level' => $m[2], 'message' => $m[3], 'details' => ''];
            } elseif ($cur) {
                $cur['details'] .= $line . "\n";
            }
        }
        if ($cur) $entries[] = $cur;
        $entries = array_filter($entries, fn ($e) => (self::LEVELS[$e['level']] ?? 0) >= $minLevel
            && ($search === '' || stripos($e['message'] . $e['details'], $search) !== false));
        foreach ($entries as &$e) {
            // Laravel hängt den Kontext als JSON an: Ausnahme-Text vorne, Rest in die Details
            if (preg_match('/^(.*?) (\{"(?:exception|userId)".*)$/s', $e['message'], $mm)) {
                $e['message'] = $mm[1];
                $e['details'] = $mm[2] . "\n" . $e['details'];
            }
            $e['details'] = rtrim($e['details']);
        }
        return array_reverse(array_values($entries));
    }

    /** Nur das Ende großer Dateien lesen */
    private static function tail(string $file, int $maxBytes): string
    {
        $size = @filesize($file);
        if (!$size) return '';
        $fh = fopen($file, 'rb');
        if ($size > $maxBytes) {
            fseek($fh, -$maxBytes, SEEK_END);
            fgets($fh); // angeschnittene Zeile verwerfen
        }
        $s = stream_get_contents($fh);
        fclose($fh);
        return (string) $s;
    }
}
