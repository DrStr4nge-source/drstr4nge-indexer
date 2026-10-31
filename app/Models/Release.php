<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    protected $guarded = [];
    protected $casts = [
        'files' => 'array', 'posted_at' => 'datetime',
        'obfuscated' => 'boolean', 'inspected' => 'boolean', 'locked' => 'boolean', 'meta_checked' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(fn (Release $r) => \App\Services\Search\ReleaseIndex::sync($r));
        static::deleted(fn (Release $r) => \App\Services\Search\ReleaseIndex::remove([$r->id]));
    }

    public function newsgroup() { return $this->belongsTo(Newsgroup::class); }
    public function media() { return $this->belongsTo(Media::class); }

    /** Nur Releases, die User sehen dürfen: komplett, kein Spam, nicht passwortgeschützt, Name bekannt. */
    public function scopeVisible($q)
    {
        return $q->where('completion', '>=', config('indexer.min_completion'))
            ->where('status', 'ok')
            ->where('obfuscated', false);
    }

    public function scopeCategory($q, array $ids)
    {
        return $ids ? $q->whereIn('category_id', $ids) : $q;
    }

    public function scopeSearch($q, ?string $term)
    {
        foreach (preg_split('/[\s.]+/', trim((string) $term), -1, PREG_SPLIT_NO_EMPTY) as $w) {
            $q->where('name', 'like', '%' . addcslashes($w, '%_') . '%');
        }
        return $q;
    }
}
