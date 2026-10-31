<?php
namespace App\Services;

/** Wandelt Bilddaten (JPEG/PNG/...) per GD in WebP um. Reine PHP-Klasse. */
class CoverImage
{
    public static function supported(): bool
    {
        return function_exists('imagewebp') && function_exists('imagecreatefromstring');
    }

    /** @return string|null WebP-Bytes, null bei Fehler oder fehlender WebP-Unterstützung */
    public static function toWebp(string $data, int $maxWidth = 342, int $quality = 80): ?string
    {
        if (!self::supported() || $data === '') {
            return null;
        }
        $im = @imagecreatefromstring($data);
        if (!$im) {
            return null;
        }
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        if (imagesx($im) > $maxWidth) {
            $scaled = imagescale($im, $maxWidth, -1, IMG_BICUBIC);
            if ($scaled) {
                $im = $scaled;
            }
        }
        ob_start();
        $ok = imagewebp($im, null, max(1, min(100, $quality)));
        $out = (string) ob_get_clean();
        return ($ok && $out !== '') ? $out : null;
    }
}
