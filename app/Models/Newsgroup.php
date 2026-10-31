<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Newsgroup extends Model
{
    protected $fillable = ['name', 'active', 'last_article'];
    protected $casts = ['active' => 'boolean'];
    public function releases() { return $this->hasMany(Release::class); }
}
