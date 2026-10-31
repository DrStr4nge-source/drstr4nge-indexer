<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Verknüpft vorhandene Releases mit Pre-Einträgen gleichen Namens (einmalig nach Import oder Update). */
class PreMatch extends Command
{
    protected $signature = 'indexer:pre-match';
    protected $description = 'Releases mit der PreDB verknüpfen';

    public function handle(): int
    {
        $n = DB::update('UPDATE releases r JOIN predb p ON p.title = r.name SET r.pre_id = p.id WHERE r.pre_id IS NULL');
        $this->info("$n Releases mit einem Pre verknüpft.");
        return self::SUCCESS;
    }
}
