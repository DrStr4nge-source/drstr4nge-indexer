<?php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAdmin extends Command
{
    protected $signature = 'indexer:make-admin {email} {--create : User anlegen, falls es ihn noch nicht gibt (Passwort wird erzeugt und angezeigt)}';
    protected $description = 'Macht einen User zum Admin (mit --create auch neu anlegen)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Ungültige E-Mail-Adresse.');
            return self::FAILURE;
        }

        $u = User::where('email', $email)->first();
        $password = null;
        if (!$u) {
            if (!$this->option('create')) {
                $this->error('User nicht gefunden. Erst registrieren oder --create nutzen.');
                return self::FAILURE;
            }
            $password = Str::password(20, symbols: false);
            $u = new User(['name' => Str::before($email, '@'), 'email' => $email, 'password' => $password]);
            $u->forceFill(['email_verified_at' => now()]);
        }
        $u->forceFill(['is_admin' => true])->save();

        $this->info("{$u->email} ist jetzt Admin.");
        if ($password !== null) {
            $this->line("Passwort: $password");
            $this->warn('Bitte nach dem ersten Login im Profil ändern.');
        }
        return self::SUCCESS;
    }
}
