<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blacklist extends Model
{
    protected $table = 'blacklist_rules';
    protected $guarded = [];
    private static ?array $cache = null;

    public static function flush(): void
    {
        self::$cache = null;
    }

    /** @param string|null $name null = Name noch unbekannt (verschleiert): Namens-Regeln überspringen */
    public static function matches(?string $poster, ?string $name): bool
    {
        self::$cache ??= self::all(['type', 'pattern'])->toArray();
        foreach (self::$cache as $r) {
            if ($r['type'] === 'poster' && $poster !== null) {
                $rx = '~^' . str_replace('\*', '.*', preg_quote($r['pattern'], '~')) . '$~i';
                if (preg_match($rx, $poster)) {
                    return true;
                }
            } elseif ($r['type'] === 'name_regex' && $name !== null && @preg_match('~' . str_replace('~', '\~', $r['pattern']) . '~i', $name) === 1) {
                return true;
            }
        }
        return false;
    }

    public static function validRegex(string $pattern): bool
    {
        return @preg_match('~' . str_replace('~', '\~', $pattern) . '~i', '') !== false;
    }
}
