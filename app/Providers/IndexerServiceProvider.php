<?php
namespace App\Providers;

use App\Support\RuntimeSettings;
use Illuminate\Support\ServiceProvider;

/** DrStr4nge Indexer: Einstellungen aus dem Admin anwenden. In bootstrap/providers.php eingetragen (install.sh/update.sh). */
class IndexerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            RuntimeSettings::apply();
        } catch (\Throwable $e) {
            // z.B. Datenbank noch nicht eingerichtet: dann gilt nur die .env
        }
    }
}
