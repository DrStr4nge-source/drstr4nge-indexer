<?php
namespace App\Services;

/** Erkennt zufällige (verschleierte) Release-Namen. Reine PHP-Klasse. */
class Obfuscation
{
    public static function looks(string $name): bool
    {
        $n = trim(preg_replace('/\.(part\d+|vol\d+\+\d+)$/i', '', $name));
        $n = preg_replace('/\.(rar|r\d{2,3}|par2|mkv|mp4|avi|zip|7z|\d{3})$/i', '', $n);
        if (strlen($n) < 12 || preg_match('/[\s._\-]/', $n)) {
            return false; // normale Namen haben Trennzeichen
        }
        if (preg_match('/^[a-f0-9]{16,}$/i', $n)) {
            return true;
        }
        return strlen($n) >= 16 && preg_match('/\d/', $n) && preg_match('/[a-z]/i', $n);
    }
}
