<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Ein Film oder eine Serie auf der Merkliste eines Users */
class Watch extends Model
{
    protected $table = 'watchlist';
    protected $guarded = [];

    public function media() { return $this->belongsTo(Media::class); }
}
