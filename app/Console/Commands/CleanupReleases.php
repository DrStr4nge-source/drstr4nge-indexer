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

        $old = $this->purge(Release::where('posted_at', '<', now()->subDays(config('indexer.retention_days'))), 'alt');
        $incomplete = $this->purge(Release::where('completion', '<', $min)->where('locked', false)
            ->where('updated_at', '<', now()->subDays(max(1, (int) config('indexer.delete_incomplete_days')))), 'unvollständig');
        $spam = $this->purge(Release::where('status', 'spam')->where('locked', false)
            ->where('updated_at', '<', now()->subDays(max(1, (int) config('indexer.delete_spam_days')))), 'Spam');

        // Verschleierte, die auch nach X Tagen keinen echten Namen bekommen haben (0 = aus)
        $obfDays = (int) config('indexer.delete_obfuscated_days');
        $obf = $obfDays > 0
            ? $this->purge(Release::where('obfuscated', true)->where('locked', false)->where('created_at', '<', now()->subDays($obfDays)), 'verschleiert')
            : 0;

        // Passwortgeschützte nach X Tagen (0 = aus); von Hand freigegebene bleiben
        $pwDays = (int) config('indexer.delete_passworded_days');
        $pw = $pwDays > 0
            ? $this->purge(Release::where('status', 'passworded')->where('locked', false)->where('created_at', '<', now()->subDays($pwDays)), 'mit Passwort')
            : 0;
        $dl = Download::where('created_at', '<', now()->subDays(90))->delete();
        $pay = Payment::where('status', 'pending')->where('created_at', '<', now()->subDays(30))->delete();

        $this->info("Gelöscht: $old alt, $incomplete unvollständig, $spam Spam, $obf verschleiert, $pw mit Passwort, $dl Download-Einträge, $pay offene Zahlungen.");
        return self::SUCCESS;
    }

    /**
     * Löscht in Blöcken und geht dabei einmal der ID nach durch die Tabelle (where id > letzte). Früher begann jede Runde
     * wieder vorne und las dieselben nicht passenden Zeilen immer wieder: bei Millionen Releases dauerte das Tage.
     */
    private function purge($query, string $label = ''): int
    {
        $total = 0;
        $last = 0;
        $max = (int) Release::max('id');
        while ($last < $max) {
            $ids = (clone $query)->where('id', '>', $last)->where('id', '<=', $last + self::WINDOW)->pluck('id');
            if ($ids->isNotEmpty()) {
                foreach ($ids->chunk(1000) as $chunk) {
                    $total += Release::whereIn('id', $chunk)->delete();
                    \App\Services\Search\ReleaseIndex::remove($chunk->values()->all());
                }
            }
            $last += self::WINDOW;
            if ($label !== '' && $this->output->isDecorated()) {
                $this->output->write(sprintf("\r%s: %s gelöscht (%d %%)", $label, number_format($total, 0, ',', '.'), min(100, (int) ($last / max(1, $max) * 100))));
            }
        }
        if ($label !== '' && $this->output->isDecorated()) {
            $this->output->write("\r" . str_repeat(' ', 60) . "\r");
        }
        return $total;
    }

    /** IDs pro Durchgang: der Primärschlüssel begrenzt die Suche, jede Zeile wird nur einmal gelesen */
    private const WINDOW = 20000;
}
