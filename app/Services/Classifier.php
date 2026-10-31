<?php
namespace App\Services;

/** Entscheidet ok | spam | passworded anhand von Name, Dateiliste und Größe. Reine PHP-Klasse. */
class Classifier
{
    private const EXEC = ['exe', 'scr', 'bat', 'com', 'cmd', 'vbs', 'vbe', 'js', 'jse', 'wsf', 'lnk', 'msi', 'pif', 'hta', 'jar'];
    private const SKIP = ['par2', 'nfo', 'sfv', 'nzb', 'txt', 'jpg', 'jpeg', 'png', 'url', 'srr', 'srs'];

    public static function status(
        string $name,
        array $files,
        int $size,
        bool $blacklisted = false,
        int $minSize = 0,
        array $spamWords = []
    ): string {
        if ($blacklisted) {
            return 'spam';
        }
        foreach ($spamWords as $w) {
            if ($w !== '' && stripos($name, $w) !== false) {
                return 'spam';
            }
        }

        $payload = [];
        foreach ($files as $f) {
            $e = strtolower(pathinfo((string) $f, PATHINFO_EXTENSION));
            if (!in_array($e, self::SKIP, true)) {
                $payload[] = $e;
            }
        }
        // Nur ausführbare Dateien = Malware-Risiko
        if ($payload && !array_diff($payload, self::EXEC)) {
            return 'spam';
        }
        if ($minSize > 0 && $size < $minSize) {
            return 'spam';
        }
        if (preg_match('/pass(w(or)?d|wort)|\{\{[^}]+\}\}|[-_.]pw[-_.]/i', $name)) {
            return 'passworded';
        }
        return 'ok';
    }
}
