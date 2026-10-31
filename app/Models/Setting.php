<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** Einfache Schlüssel/Wert-Einstellungen (z.B. Seitenname, Logo), änderbar im Admin-Bereich. */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$cache === null) {
            try {
                self::$cache = Schema::hasTable('settings') ? self::query()->pluck('value', 'key')->all() : [];
            } catch (\Throwable $e) {
                self::$cache = []; // z.B. Datenbank noch nicht eingerichtet
            }
        }
        $v = self::$cache[$key] ?? null;
        return ($v === null || $v === '') ? $default : $v;
    }

    public static function put(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        self::$cache = null;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
