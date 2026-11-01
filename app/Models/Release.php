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

    /** Pre-Daten immer mitladen (eine Abfrage pro Liste) */
    protected $with = ['pre'];

    protected static function booted(): void
    {
        // Neuer oder aufgelöster Name: passendes Pre suchen
        static::saving(function (Release $r) {
            if ($r->isDirty('name') && $r->name) {
                $r->pre_id = Predb::where('title', $r->name)->value('id');
            }
        });
        static::saved(fn (Release $r) => \App\Services\Search\ReleaseIndex::sync($r));
        static::deleted(fn (Release $r) => \App\Services\Search\ReleaseIndex::remove([$r->id]));
    }

    public function newsgroup() { return $this->belongsTo(Newsgroup::class); }
    public function media() { return $this->belongsTo(Media::class); }
    public function pre() { return $this->belongsTo(Predb::class, 'pre_id'); }

    /** Nur Releases, die User sehen dürfen: komplett, kein Spam, nicht passwortgeschützt, Name bekannt. */
    public function scopeVisible($q)
    {
        return $q->where('completion', '>=', config('indexer.min_completion'))
            ->where('status', 'ok')
            ->where('obfuscated', false);
    }

    /**
     * Alle Spalten außer "files" (Artikel-Liste, bei großen Releases mehrere MB). Für Listen und Suche,
     * die nur anzeigen. Gespeichert werden kann so ein Release trotzdem (nur geänderte Felder).
     */
    public function scopeLite($q)
    {
        static $cols = null;
        $cols ??= array_values(array_diff(\Illuminate\Support\Facades\Schema::getColumnListing($this->getTable()), ['files']));
        return $q->select(array_map(fn ($c) => $this->getTable() . '.' . $c, $cols));
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
