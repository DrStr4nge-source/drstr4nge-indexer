<?php
namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * Artikel-Liste eines Releases. Große Listen werden komprimiert gespeichert ("gz:" + base64(deflate(json))),
 * meist 6- bis 10-mal kleiner. Das spart Platz und hält auch riesige Releases (Hunderte Teile) unter dem
 * Paket-Limit der Datenbank. Alte, unkomprimierte JSON-Einträge werden weiter gelesen.
 */
class ReleaseFiles implements CastsAttributes
{
    public const MIN_COMPRESS = 4096; // Bytes; kleine Listen bleiben lesbares JSON

    public function get($model, string $key, $value, array $attributes): array
    {
        return self::decode($value);
    }

    public function set($model, string $key, $value, array $attributes): string
    {
        return self::encode(is_array($value) ? $value : self::decode($value));
    }

    public static function encode(array $files): string
    {
        $json = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if (strlen($json) < self::MIN_COMPRESS) {
            return $json;
        }
        return 'gz:' . base64_encode(gzdeflate($json, 6));
    }

    public static function decode(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (str_starts_with($value, 'gz:')) {
            $raw = @gzinflate(base64_decode(substr($value, 3), true) ?: '');
            $value = $raw === false ? '[]' : $raw;
        }
        $d = json_decode($value, true);
        return is_array($d) ? $d : [];
    }
}
