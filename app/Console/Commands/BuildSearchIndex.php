<?php
namespace App\Console\Commands;

use App\Models\Release;
use App\Services\Search\ReleaseIndex;
use Illuminate\Console\Command;

class BuildSearchIndex extends Command
{
    protected $signature = 'indexer:search-index {--fresh : Index vorher leeren}';
    protected $description = 'Legt den Manticore-Index an und füllt ihn mit allen sichtbaren Releases';

    public function handle(): int
    {
        if (!ReleaseIndex::enabled()) {
            $this->error('Manticore ist aus. MANTICORE_ENABLED=true in der .env setzen.');
            return self::FAILURE;
        }
        $client = ReleaseIndex::client();
        try {
            $client->createTable();
            if ($this->option('fresh')) {
                $client->truncate();
            }
            $n = 0;
            Release::visible()->select(['id', 'name', 'category_id', 'posted_at', 'size'])
                ->chunkById(1000, function ($chunk) use ($client, &$n) {
                    $client->upsert($chunk->map(fn ($r) => ReleaseIndex::row($r))->all());
                    $n += $chunk->count();
                    $this->line("$n Releases indexiert ...");
                });
        } catch (\Throwable $e) {
            $this->error('Manticore nicht erreichbar oder Fehler: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->info("Fertig: $n Releases im Suchindex.");
        return self::SUCCESS;
    }
}
