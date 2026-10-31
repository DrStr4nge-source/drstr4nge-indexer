<?php
namespace App\Support;

use App\Models\Setting;

/** Registrierungs-Modus: open (jeder), invite (nur mit Code), closed (niemand). */
class Registration
{
    public const MODES = ['open' => 'Offen für alle', 'invite' => 'Nur mit Einladungscode', 'closed' => 'Geschlossen'];

    public static function mode(): string
    {
        $m = (string) Setting::get('registration_mode', 'open');
        return array_key_exists($m, self::MODES) ? $m : 'open';
    }

    public static function allowed(): bool
    {
        return self::mode() !== 'closed';
    }
}
