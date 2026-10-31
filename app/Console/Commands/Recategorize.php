<?php
namespace App\Console\Commands;

use App\Models\Release;
use App\Services\Categorizer;
use App\Services\ReleaseBuilder;
use Illuminate\Console\Command;

class Recategorize extends Command
{
    protected $signature = 'indexer:recategorize';
    protected $description = 'Ordnet alle Releases neu ein (Kategorie, Spam-Status, Verschleierung)';

    public function handle(ReleaseBuilder $builder): int
    {
        $n = 0;
        Release::chunkById(500, function ($chunk) use ($builder, &$n) {
            foreach ($chunk as $rel) {
                if (!$rel->inspected) {
                    $rel->obfuscated = \App\Services\Obfuscation::looks($rel->name);
                }
                $builder->classify($rel);
                $rel->save();
                $n++;
            }
        });
        $this->info("$n Releases neu eingeordnet.");
        return self::SUCCESS;
    }
}
