<?php
namespace App\Console\Commands;

use App\Models\Media;
use App\Services\TvMaze;
use Illuminate\Console\Command;

/** Ergänzt bei Serien TVDB-/IMDb-/TVmaze-IDs (stündlich, höchstens 100 pro Lauf). */
class FetchTvMaze extends Command
{
    protected $signature = 'indexer:tvmaze {--limit=100}';
    protected $description = 'Serien-IDs von TVmaze ergänzen';

    public function handle(): int
    {
        if (!config('indexer.tvmaze')) {
            $this->info('TVmaze ist aus (TVMAZE_ENABLED=false).');
            return self::SUCCESS;
        }
        $todo = Media::where('type', 'tv')
            ->where(fn ($q) => $q->whereNull('tvmaze_checked_at')->orWhere(fn ($q) => $q->whereNull('tvmaze_id')->where('tvmaze_checked_at', '<', now()->subDays(14))))
            ->orderBy('id')->limit((int) $this->option('limit'))->get();
        $n = 0;
        foreach ($todo as $m) {
            try {
                $r = TvMaze::find($m->tvdb_id, $m->imdb_id, $m->title, $m->year);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());
                break;
            }
            if ($r) {
                $m->tvmaze_id = $r['tvmaze_id'];
                $m->tvdb_id ??= $r['tvdb_id'];
                $m->imdb_id ??= $r['imdb_id'];
                $n++;
            }
            $m->tvmaze_checked_at = now();
            $m->save();
            usleep(600000); // TVmaze erlaubt etwa 20 Anfragen in 10 Sekunden
        }
        $this->info(sprintf('%d Serien geprüft, %d bei TVmaze gefunden.', $todo->count(), $n));
        return self::SUCCESS;
    }
}
