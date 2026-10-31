<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $table = 'media';
    protected $guarded = [];
    protected $casts = ['details_at' => 'datetime'];

    public function releases()
    {
        return $this->hasMany(Release::class);
    }

    public function coverUrl(): ?string
    {
        // asset() nimmt die Adresse, mit der die Seite aufgerufen wird (nicht APP_URL)
        return $this->cover ? asset('storage/' . ltrim($this->cover, '/')) : null;
    }

    public function imdbUrl(): ?string
    {
        return $this->imdb_id ? 'https://www.imdb.com/title/' . $this->imdb_id . '/' : null;
    }

    public function tmdbUrl(): ?string
    {
        return $this->tmdb_id ? 'https://www.themoviedb.org/' . ($this->type === 'tv' ? 'tv' : 'movie') . '/' . $this->tmdb_id : null;
    }
}
