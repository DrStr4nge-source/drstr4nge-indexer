<?php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** Notfall: Zwei-Faktor für einen User ausschalten (Handy verloren, keine Codes mehr) */
class TwoFactorReset extends Command
{
    protected $signature = 'indexer:2fa-reset {email}';
    protected $description = 'Zwei-Faktor-Anmeldung eines Users ausschalten';

    public function handle(): int
    {
        $u = User::where('email', $this->argument('email'))->first();
        if (!$u) {
            $this->error('User nicht gefunden.');
            return self::FAILURE;
        }
        $u->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        \Illuminate\Support\Facades\Log::warning("Zwei-Faktor per Befehl zurückgesetzt für {$u->email}");
        $this->info("Zwei-Faktor für {$u->email} ausgeschaltet. Nach dem Login im Admin neu einrichten.");
        return self::SUCCESS;
    }
}
