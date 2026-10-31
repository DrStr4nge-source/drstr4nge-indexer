<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Payment extends Model
{
    protected $guarded = [];

    public function user() { return $this->belongsTo(User::class); }

    /** Zahlung als bezahlt markieren und VIP verlängern. Mehrfache Rückmeldungen verlängern nur einmal. */
    public function markPaid(): bool
    {
        return DB::transaction(function () {
            $p = self::whereKey($this->id)->lockForUpdate()->first();
            if (!$p || $p->status === 'paid') {
                return false;
            }
            $p->update(['status' => 'paid']);
            $user = User::whereKey($p->user_id)->lockForUpdate()->first();
            if ($user) {
                $base = $user->isVip() ? $user->vip_until : now();
                $user->forceFill(['vip_until' => $base->copy()->addMonths((int) config("indexer.plans.{$p->plan}.months", 1))])->save();
            }
            $this->status = 'paid';
            return true;
        });
    }
}
