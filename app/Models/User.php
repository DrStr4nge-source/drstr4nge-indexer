<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'vip_until' => 'datetime',
            'is_admin' => 'boolean',
            'is_banned' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn ($u) => $u->api_key ??= Str::random(32));
    }

    public function isVip(): bool
    {
        return $this->vip_until && $this->vip_until->isFuture();
    }

    public function grabsToday(): int
    {
        return Download::where('user_id', $this->id)->where('created_at', '>=', now()->startOfDay())->count();
    }

    public function canGrab(): bool
    {
        return $this->isVip() || $this->grabsToday() < config('indexer.free_grabs_per_day');
    }
}
