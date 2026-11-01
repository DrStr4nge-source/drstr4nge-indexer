<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Newsgroup extends Model
{
    protected $fillable = ['name', 'active', 'last_article', 'backfill_days'];
    protected $casts = ['active' => 'boolean', 'backfill_days' => 'integer'];

    /** Backfill-Tage dieser Gruppe (eigener Wert oder der allgemeine) */
    public function backfillDays(): int
    {
        return $this->backfill_days ?? (int) config('indexer.backfill_days');
    }
    public function releases() { return $this->hasMany(Release::class); }
}
