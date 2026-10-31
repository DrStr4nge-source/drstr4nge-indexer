<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ein Pre: wann ein Release in der Szene erschienen ist, ggf. mit Nuke. Quelle: IRC-Pre-Kanäle. */
class Predb extends Model
{
    public const NONE = 0;
    public const NUKED = 1;
    public const UNNUKED = 2;
    public const MODNUKED = 3;
    public const RENUKED = 4;
    public const OLDNUKE = 5;

    protected $table = 'predb';
    protected $guarded = [];
    protected $casts = ['predate' => 'datetime'];

    /** Gilt als genuked (UNNUKED zählt nicht). */
    public function isNuked(): bool
    {
        return in_array((int) $this->nuked, [self::NUKED, self::MODNUKED, self::RENUKED, self::OLDNUKE], true);
    }

    public function nukeLabel(): ?string
    {
        return match ((int) $this->nuked) {
            self::NUKED => 'NUKED', self::MODNUKED => 'MODNUKE', self::RENUKED => 'RENUKED',
            self::OLDNUKE => 'OLDNUKE', self::UNNUKED => 'UNNUKED', default => null,
        };
    }

    public function releases() { return $this->hasMany(Release::class, 'pre_id'); }
}
