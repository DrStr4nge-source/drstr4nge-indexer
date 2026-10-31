<?php
namespace App\Support;

class Format
{
    /** 1536 => "1,5 KB"; deutsche Schreibweise */
    public static function bytes(int|float|null $b): string
    {
        $b = (float) $b;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($b >= 1024 && $i < count($units) - 1) {
            $b /= 1024;
            $i++;
        }
        return number_format($b, $i === 0 ? 0 : 1, ',', '.') . ' ' . $units[$i];
    }

    /** Zeitabstand kurz auf Deutsch: "vor 5 Min", "vor 3 Std", "vor 2 Tagen" */
    public static function ago(?\DateTimeInterface $t): string
    {
        if (!$t) {
            return '';
        }
        $s = max(0, time() - $t->getTimestamp());
        return match (true) {
            $s < 60 => 'gerade eben',
            $s < 3600 => 'vor ' . intdiv($s, 60) . ' Min',
            $s < 86400 => 'vor ' . intdiv($s, 3600) . ' Std',
            $s < 86400 * 2 => 'vor 1 Tag',
            $s < 86400 * 365 => 'vor ' . intdiv($s, 86400) . ' Tagen',
            default => $t->format('d.m.Y'),
        };
    }

    /** Datum/Uhrzeit in der Anzeige-Zeitzone (INDEXER_TIMEZONE, Standard Europe/Berlin). Gespeichert wird weiter in UTC. */
    public static function dt(mixed $t, string $format = 'd.m.Y H:i'): string
    {
        if ($t === null || $t === '') {
            return '';
        }
        $c = $t instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::instance($t) : \Illuminate\Support\Carbon::parse((string) $t);
        return $c->copy()->setTimezone(config('indexer.timezone', 'Europe/Berlin'))->format($format);
    }
}
