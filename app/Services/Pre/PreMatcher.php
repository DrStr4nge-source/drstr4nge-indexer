<?php
namespace App\Services\Pre;

/**
 * Ordnet ein verschleiertes Release einem Pre zu: Größe, Dateianzahl und Zeit müssen passen,
 * und es darf genau ein Pre in Frage kommen. Lieber kein Treffer als ein falscher.
 */
class PreMatcher
{
    private const DATA_SKIP = '/\.(par2|nfo|sfv|nzb|txt|jpg|jpeg|png|url|srr|srs)$/i';
    public const YENC_OVERHEAD = 1.03; // Usenet-Bytes sind durch yEnc und Header etwa 2-3 % größer

    /**
     * Größe und Dateianzahl, die mit der PreDB verglichen werden.
     * @param array $rel ['real_size','real_files','files' => [name => ['segs' => [[bytes]], 'total']]]
     * @return array{size:int,files:int,exact:bool}|null null = nicht sicher genug bestimmbar
     */
    public static function measure(array $rel): ?array
    {
        if (!empty($rel['real_size']) && !empty($rel['real_files'])) {
            return ['size' => (int) $rel['real_size'], 'files' => (int) $rel['real_files'], 'exact' => true];
        }
        // Ohne PAR2: nur wenn alle Dateinamen eine Endung haben (sonst sind PAR2-Teile nicht erkennbar)
        $size = 0;
        $n = 0;
        foreach ((array) ($rel['files'] ?? []) as $name => $f) {
            if (!preg_match('/\.[A-Za-z0-9]{2,4}$/', (string) $name)) {
                return null;
            }
            if (preg_match(self::DATA_SKIP, (string) $name)) {
                continue;
            }
            $size += array_sum(array_column((array) $f['segs'], 'bytes'));
            $n++;
        }
        return $n > 0 ? ['size' => (int) round($size / self::YENC_OVERHEAD), 'files' => $n, 'exact' => false] : null;
    }

    /** Toleranz in Bytes: genau (PAR2) 0,5 % bzw. mind. 6 MB (Rundung "4.37GB"), geschätzt 3 % */
    public static function tolerance(int $size, bool $exact): int
    {
        return $exact ? max((int) ($size * 0.005), 6 * 1024 * 1024) : (int) ($size * 0.03);
    }

    /**
     * @param array $m Ergebnis von measure()
     * @param array $candidates Pres im Größen- und Zeitfenster: [['id','size','files','title'], ...]
     * @return array|null der eine passende Pre
     */
    public static function pick(array $m, array $candidates): ?array
    {
        $tol = self::tolerance($m['size'], $m['exact']);
        $fit = array_values(array_filter($candidates, function ($p) use ($m, $tol) {
            if (empty($p['size']) || abs((int) $p['size'] - $m['size']) > $tol) {
                return false;
            }
            if (!empty($p['files'])) {
                // Pre zählt oft .nfo/.sfv mit, PAR2 nicht: kleine Abweichung erlaubt
                return abs((int) $p['files'] - $m['files']) <= ($m['exact'] ? 3 : 2);
            }
            return $m['exact']; // ohne Dateianzahl nur bei genauer Größe
        }));
        return count($fit) === 1 ? $fit[0] : null;
    }
}
