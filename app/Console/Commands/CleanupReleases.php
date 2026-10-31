<?php
namespace App\Console\Commands;

use App\Models\Download;
use App\Models\Payment;
use App\Models\Release;
use Illuminate\Console\Command;

class CleanupReleases extends Command
{
    protected $signature = 'indexer:cleanup';
    protected $description = 'Löscht alte, unvollständige, verschleierte, passwortgeschützte und Spam-Releases sowie alte Logs';

    public function handle(): int
    {
        $min = config('indexer.min_completion');

        $old = $this->purge(Release::where('posted_at', '<', now()->subDays(config('indexer.retention_days'))));
        $incomplete = $this->purge(Release::where('completion', '<', $min)->where('updated_at', '<', now()->subDays(2)));
        $spam = $this->purge(Release::where('status', 'spam')->where('locked', false)->where('updated_at', '<', now()->subDays(7)));

        // Verschleierte, die auch nach X Tagen keinen echten Namen bekommen haben (0 = aus)
        $obfDays = (int) config('indexer.delete_obfuscated_days');
        $obf = $obfDays > 0
            ? $this->purge(Release::where('obfuscated', true)->where('locked', false)->where('created_at', '<', now()->subDays($obfDays)))
            : 0;

        // Passwortgeschützte nach X Tagen (0 = aus); von Hand freigegebene bleiben
        $pwDays = (int) config('indexer.delete_passworded_days');
        $pw = $pwDays > 0
            ? $this->purge(Release::where('status', 'passworded')->where('locked', false)->where('created_at', '<', now()->subDays($pwDays)))
            : 0;
        $dl = Download::where('created_at', '<', now()->subDays(90))->delete();
        $pay = Payment::where('status', 'pending')->where('created_at', '<', now()->subDays(30))->delete();

        $this->info("Gelöscht: $old alt, $incomplete unvollständig, $spam Spam, $obf verschleiert, $pw mit Passwort, $dl Download-Einträge, $pay offene Zahlungen.");
        return self::SUCCESS;
    }

    private function purge($query): int
    {
        $total = 0;
        while (($ids = (clone $query)->limit(1000)->pluck('id'))->isNotEmpty()) {
            $total += Release::whereIn('id', $ids)->delete();
            \App\Services\Search\ReleaseIndex::remove($ids->all());
        }
        return $total;
    }
}
