<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Meldung eines Users zu einem Release (defekt, Passwort, falscher Name, Spam) */
class ReleaseReport extends Model
{
    public const REASONS = ['broken' => 'Defekt / unvollständig', 'password' => 'Passwort', 'wrong' => 'Falscher Name / falscher Inhalt', 'spam' => 'Spam / Virus', 'other' => 'Sonstiges'];

    protected $guarded = [];

    public function release() { return $this->belongsTo(Release::class); }
    public function user() { return $this->belongsTo(User::class); }
}
