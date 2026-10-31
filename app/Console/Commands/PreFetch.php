<?php
namespace App\Console\Commands;

use App\Models\Predb;
use App\Models\Release;
use App\Services\Pre\PredbNet;
use App\Services\Pre\PreRss;
use App\Services\Pre\PreStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Holt neue Pres von predb.net und sucht optional Pres für Releases ohne Pre. */
class PreFetch extends Command
{
    protected $signature = 'indexer:pre-fetch {--pages= : höchstens so viele Seiten neueste Einträge} {--lookup= : so viele Releases ohne Pre einzeln nachschlagen}';
    protected $description = 'Pre-Daten von predb.net und RSS-Feeds holen';

    public function handle(): int
    {
        $rssOk = $this->rss();
        if (!config('indexer.predb_api.enabled')) {
            $this->info('predb.net ist aus (PREDB_API_ENABLED=false).');
            return $rssOk ? self::SUCCESS : self::FAILURE;
        }
        $pages = max(1, (int) ($this->option('pages') ?: config('indexer.predb_api.pages')));
        $pause = (int) config('indexer.predb_api.pause_ms') * 1000;
        $new = $upd = 0;
        try {
            // Neueste Seiten, bis eine Seite nur Bekanntes enthält
            for ($p = 1; $p <= $pages; $p++) {
                $rows = PredbNet::latest($p);
                if (!$rows) {
                    break;
                }
                $known = Predb::whereIn('title', array_column($rows, 'title'))->count();
                foreach ($rows as $row) {
                    [, $isNew] = PreStore::save($row);
                    $isNew ? $new++ : $upd++;
                }
                if ($known === count($rows)) {
                    break;
                }
                usleep($pause);
            }

            // Einzelne Releases ohne Pre nachschlagen (neueste zuerst)
            $lookup = (int) ($this->option('lookup') ?? config('indexer.predb_api.lookup'));
            if ($lookup > 0) {
                $found = 0;
                $rels = Release::whereNull('pre_id')->where('obfuscated', false)->where('status', 'ok')
                    ->where('created_at', '>=', now()->subDays(30))
                    ->where(fn ($q) => $q->whereNull('pre_checked_at')->orWhere('pre_checked_at', '<', now()->subDays(3)))
                    ->orderByDesc('id')->limit($lookup)->get();
                foreach ($rels as $rel) {
                    foreach (PredbNet::search($rel->name) as $row) {
                        if (strcasecmp($row['title'], $rel->name) === 0) {
                            [$pre] = PreStore::save($row);
                            $rel->pre_id = $pre->id;
                            $found++;
                            break;
                        }
                    }
                    $rel->pre_checked_at = now();
                    $rel->saveQuietly();
                    usleep($pause);
                }
                $this->info("Nachgeschlagen: {$rels->count()} Releases, $found mit Pre gefunden.");
            }
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            $this->info("Bis dahin: $new neu, $upd aktualisiert.");
            return self::FAILURE;
        }
        Cache::forever('predb_api.last_at', time());
        $this->info("predb.net: $new neu, $upd aktualisiert.");
        return $rssOk ? self::SUCCESS : self::FAILURE;
    }

    /** RSS-Feeds aus PRE_RSS_FEEDS; ein kaputter Feed stoppt die anderen nicht. */
    private function rss(): bool
    {
        $ok = true;
        foreach ((array) config('indexer.pre_rss') as $url) {
            try {
                $new = 0;
                foreach (PreRss::fetch($url) as $row) {
                    [, $isNew] = PreStore::save($row);
                    $new += $isNew ? 1 : 0;
                }
                $this->info(parse_url($url, PHP_URL_HOST) . ": $new neu");
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                $ok = false;
            }
        }
        if ($ok && config('indexer.pre_rss')) {
            Cache::forever('pre_rss.last_at', time());
        }
        return $ok;
    }
}
