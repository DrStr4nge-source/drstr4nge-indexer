<?php
namespace App\Providers;

use App\Support\RuntimeSettings;
use Illuminate\Support\ServiceProvider;

/** DrStr4nge Indexer: Einstellungen aus dem Admin anwenden. In bootstrap/providers.php eingetragen (install.sh/update.sh). */
class IndexerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Gesperrte Accounts überall abmelden (bootstrap/app.php bleibt unverändert)
        $this->app['router']->pushMiddlewareToGroup('web', \App\Http\Middleware\BlockBanned::class);
        // Passwörter für Usenet, Mail, MEGA und IRC genau so speichern, wie eingegeben (Leerzeichen am Rand gehören dazu)
        if (method_exists(\Illuminate\Foundation\Http\Middleware\TrimStrings::class, 'except')) {
            $secrets = array_keys(array_filter(RuntimeSettings::FIELDS, fn ($f) => $f[1] === 'secret'));
            \Illuminate\Foundation\Http\Middleware\TrimStrings::except(array_merge($secrets, ['mega_pass', 'irc_password']));
        }
        try {
            RuntimeSettings::apply();
        } catch (\Throwable $e) {
            // z.B. Datenbank noch nicht eingerichtet: dann gilt nur die .env
        }
    }
}
