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
    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'vip_until' => 'datetime',
            'is_admin' => 'boolean',
            'is_banned' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null && !empty($this->two_factor_secret);
    }

    /** Wiederherstellungscode einlösen (jeder nur einmal) */
    public function useRecoveryCode(string $code): bool
    {
        $hash = hash('sha256', strtolower(trim($code)));
        $codes = (array) $this->two_factor_recovery_codes;
        if (!in_array($hash, $codes, true)) {
            return false;
        }
        $this->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$hash]))])->save();
        return true;
    }

    /** @return string[] neue Codes im Klartext (nur einmal anzeigen) */
    public function newRecoveryCodes(): array
    {
        $plain = [];
        for ($i = 0; $i < 8; $i++) {
            $plain[] = strtolower(Str::random(5) . '-' . Str::random(5));
        }
        $this->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => hash('sha256', $c), $plain)])->save();
        return $plain;
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
