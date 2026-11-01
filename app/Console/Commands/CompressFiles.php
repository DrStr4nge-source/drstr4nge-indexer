<?php
namespace App\Console\Commands;

use App\Casts\ReleaseFiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Komprimiert die Artikel-Listen vorhandener Releases (neue werden seit 1.13.5 automatisch komprimiert). */
class CompressFiles extends Command
{
    protected $signature = 'indexer:compress-files';
    protected $description = 'Artikel-Listen vorhandener Releases komprimieren (spart viel Speicherplatz)';

    public function handle(): int
    {
        $before = $after = $n = 0;
        $last = 0;
        while (true) {
            $rows = DB::table('releases')->select('id', 'files')->where('id', '>', $last)
                ->where('files', 'not like', 'gz:%')->orderBy('id')->limit(500)->get();
            if ($rows->isEmpty()) {
                break;
            }
            foreach ($rows as $r) {
                $last = $r->id;
                if (strlen((string) $r->files) < ReleaseFiles::MIN_COMPRESS) {
                    continue;
                }
                $new = ReleaseFiles::encode(ReleaseFiles::decode($r->files));
                DB::table('releases')->where('id', $r->id)->update(['files' => $new]); // ohne Model: Zeitstempel bleiben
                $before += strlen((string) $r->files);
                $after += strlen($new);
                $n++;
            }
            $this->output->write(sprintf("\r%s Releases komprimiert, %s gespart", number_format($n, 0, ',', '.'), \App\Support\Format::bytes($before - $after)));
        }
        $this->newLine();
        $this->info(sprintf('Fertig: %s Releases, %s → %s.', number_format($n, 0, ',', '.'), \App\Support\Format::bytes($before), \App\Support\Format::bytes($after)));
        if ($n) {
            $this->line('Damit die Datei der Datenbank kleiner wird: sudo mysql ' . config('database.connections.' . config('database.default') . '.database') . ' -e "OPTIMIZE TABLE releases;"');
        }
        return self::SUCCESS;
    }
}
