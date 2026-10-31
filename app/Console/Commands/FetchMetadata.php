<?php
namespace App\Console\Commands;

use App\Models\Media;
use App\Models\Release;
use App\Services\CoverImage;
use App\Services\Metadata;
use App\Services\RefreshableProvider;
use App\Services\TitleParser;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FetchMetadata extends Command
{
    protected $signature = 'indexer:metadata {--limit=200 : Releases pro Lauf} {--retry : Bisher nicht gefundene erneut versuchen} {--details=50 : Wie viele bekannte Titel pro Lauf Details nachladen}';
    protected $description = 'Verknüpft Film- und Serien-Releases mit dem gewählten Metadaten-Anbieter und lädt Cover als WebP';

    public function handle(): int
    {
        $provider = Metadata::provider();
        if (!$provider->enabled()) {
            $this->error('Kein Metadaten-Anbieter aktiv. METADATA_PROVIDER und der API-Key des Anbieters (z.B. TMDB_API_KEY) müssen in der .env stehen.');
            return self::FAILURE;
        }
        if (!CoverImage::supported()) {
            $this->warn('PHP-GD ohne WebP-Unterstützung: Metadaten werden geholt, aber keine Cover gespeichert.');
        }
        if ($this->option('retry')) {
            Release::where('meta_checked', true)->whereNull('media_id')->update(['meta_checked' => false]);
        }

        $todo = Release::visible()->where('meta_checked', false)
            ->where(fn ($q) => $q->whereBetween('category_id', [2000, 2999])->orWhereBetween('category_id', [5000, 5999]))
            ->orderByDesc('id')->limit((int) $this->option('limit'))->get();

        $linked = $missed = 0;
        foreach ($todo as $rel) {
            $p = TitleParser::parse($rel->name);
            if (!$p) {
                $rel->update(['meta_checked' => true]);
                continue;
            }
            $key = 'meta:' . $provider->name() . ':' . $p['type'] . ':' . TitleParser::normalize($p['title']) . ':' . ($p['year'] ?? '');

            try {
                $mediaId = Cache::get($key);
                if ($mediaId === null || ($mediaId === 0 && $this->option('retry'))) { // --retry ignoriert gemerkte Fehlschläge
                    $found = $provider->find($p);
                    $media = $found ? $this->store($found, $provider instanceof RefreshableProvider) : null;
                    $mediaId = $media?->id ?? 0; // 0 = bekannter Fehlschlag
                    Cache::put($key, $mediaId, now()->addDays(30));
                    usleep(60_000); // Anbieter nicht überfahren
                }
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());
                break; // Rest beim nächsten Lauf
            }

            $rel->update(['media_id' => $mediaId ?: null, 'meta_checked' => true]);
            $mediaId ? $linked++ : $missed++;
        }

        $this->info("Verknüpft: $linked, nicht gefunden: $missed.");

        // Bereits bekannte Titel ohne Details (Genre, Regie, Darsteller) nachladen, pro Lauf begrenzt
        if ($provider instanceof RefreshableProvider) {
            $updated = 0;
            $stale = Media::where('source', $provider->name())->whereNull('details_at')->orderBy('id')
                ->limit((int) $this->option('details'))->get();
            foreach ($stale as $m) {
                try {
                    $d = $provider->details($m->type, (int) $m->source_id);
                } catch (\RuntimeException $e) {
                    $this->error($e->getMessage());
                    break;
                }
                if ($d) {
                    $m->fill(Arr::only(array_filter($d, fn ($v) => $v !== null), ['imdb_id', 'tvdb_id', 'genres', 'director', 'actors', 'overview', 'rating']));
                }
                $m->details_at = now();
                $m->save();
                $updated++;
                usleep(60_000);
            }
            if ($updated) {
                $this->info("Details nachgeladen: $updated Titel.");
            }
        }
        return self::SUCCESS;
    }

    /** @param bool $hasDetails find() hat Genre/Regie/Darsteller schon mitgeliefert */
    private function store(array $f, bool $hasDetails = false): Media
    {
        $media = Media::firstOrNew(['type' => $f['type'], 'source' => $f['source'], 'source_id' => $f['source_id']]);
        $media->fill(Arr::only($f, ['tmdb_id', 'imdb_id', 'tvdb_id', 'title', 'year', 'overview', 'rating', 'genres', 'director', 'actors']));
        if ($hasDetails || isset($f['genres']) || isset($f['director']) || isset($f['actors'])) {
            $media->details_at = now();
        }
        $media->poster_path = $f['poster_url'] ?? $media->poster_path; // Spalte enthält die volle Bild-URL

        if (!$media->cover && $media->poster_path && CoverImage::supported()) {
            $raw = $this->download($media->poster_path);
            $webp = $raw ? CoverImage::toWebp($raw, (int) config('indexer.cover.width'), (int) config('indexer.cover.quality')) : null;
            if ($webp) {
                $path = "covers/{$media->source}-{$media->type}-{$media->source_id}.webp";
                Storage::disk('public')->put($path, $webp);
                $media->cover = $path;
            }
        }
        $media->save();
        return $media;
    }

    private function download(string $url): ?string
    {
        try {
            $res = Http::timeout(20)->get($url);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Bild-Download fehlgeschlagen: ' . $e->getMessage());
        }
        return $res->successful() ? $res->body() : null;
    }
}
