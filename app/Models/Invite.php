<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Invite extends Model
{
    protected $guarded = [];
    protected $casts = ['used_at' => 'datetime'];

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function user() { return $this->belongsTo(User::class, 'used_by'); }

    /** Lesbarer Code ohne verwechselbare Zeichen, z.B. "K7QM-2XRP-9HTD". */
    public static function newCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $c = '';
            for ($i = 0; $i < 12; $i++) {
                $c .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = implode('-', str_split($c, 4));
        } while (self::where('code', $code)->exists());
        return $code;
    }

    public static function normalize(?string $code): string
    {
        $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code));
        return implode('-', str_split($c, 4));
    }
}
