<?php
namespace App\Console\Commands;

use App\Models\Release;
use App\Services\Categorizer;
use App\Services\ReleaseBuilder;
use Illuminate\Console\Command;

class Recategorize extends Command
{
    protected $signature = 'indexer:recategorize {--if-changed : nur wenn sich die Blacklist geändert hat (für den Zeitplan)}';
    protected $description = 'Ordnet alle Releases neu ein (Kategorie, Spam-Status, Verschleierung)';

    public function handle(ReleaseBuilder $builder): int
    {
        if ($this->option('if-changed')) {
            $rules = (int) \Illuminate\Support\Facades\Cache::get('categorizer.version') !== \App\Services\Categorizer::VERSION;
            if (!\Illuminate\Support\Facades\Cache::pull('blacklist.changed') && !$rules) {
                return self::SUCCESS;
            }
            $this->info($rules ? 'Kategorie-Regeln wurden geändert (Update), ordne alle Releases neu ein.' : 'Blacklist wurde geändert, prüfe alle Releases neu.');
        }
        $n = 0;
        Release::chunkById(500, function ($chunk) use ($builder, &$n) {
            foreach ($chunk as $rel) {
                if (!$rel->inspected) {
                    // Neu bewerten, aber "kein brauchbarer Name" (z.B. "1" aus einem verschleierten Post) bleibt verschleiert
                    $rel->obfuscated = \App\Services\Obfuscation::looks($rel->name)
                        || ($rel->obfuscated && !\App\Services\SubjectParser::meaningful($rel->name));
                }
                $builder->classify($rel);
                if ($rel->status === 'spam' && !$rel->locked && !config('indexer.store_spam') && $rel->files !== []) {
                    $rel->files = []; // Spam braucht keine Artikel-Liste (INDEXER_STORE_SPAM=false)
                }
                $rel->save();
                $n++;
            }
        });
        \Illuminate\Support\Facades\Cache::forever('categorizer.version', \App\Services\Categorizer::VERSION);
        $this->info("$n Releases neu eingeordnet.");
        return self::SUCCESS;
    }
}
