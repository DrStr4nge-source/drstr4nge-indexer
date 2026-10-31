<?php
namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** Sucht Filme und Serien bei TMDB und liefert auch IMDb- und TVDB-IDs. */
class Tmdb implements MetadataProvider, RefreshableProvider
{
    private const BASE = 'https://api.themoviedb.org/3';

    public function name(): string
    {
        return 'tmdb';
    }

    public function enabled(): bool
    {
        return (string) config('indexer.tmdb.key') !== '';
    }

    /**
     * @param array{type:string,title:string,year:?int} $p Ergebnis von TitleParser::parse()
     * @return array|null null = kein sicherer Treffer
     * @throws \RuntimeException bei Netzwerk-, Key- oder Limit-Problemen (dann nicht als "geprüft" markieren)
     */
    public function find(array $p): ?array
    {
        $isTv = $p['type'] === 'tv';
        $params = ['query' => $p['title'], 'include_adult' => 'false'];
        if ($p['year']) {
            $params[$isTv ? 'first_air_date_year' : 'year'] = $p['year'];
        }
        $results = $this->get($isTv ? '/search/tv' : '/search/movie', $params)['results'] ?? [];

        $min = (float) config('indexer.tmdb.min_match');
        $hit = null;
        foreach (array_slice($results, 0, 5) as $r) {
            $titles = array_filter([$r[$isTv ? 'name' : 'title'] ?? null, $r[$isTv ? 'original_name' : 'original_title'] ?? null]);
            $score = 0.0;
            foreach ($titles as $t) {
                $score = max($score, TitleParser::similarity($p['title'], $t));
            }
            $date = $r[$isTv ? 'first_air_date' : 'release_date'] ?? '';
            $year = $date !== '' ? (int) substr($date, 0, 4) : null;
            // Film: Jahr darf höchstens 1 abweichen (Release-Jahr vs. Kinostart)
            if (!$isTv && $p['year'] && $year && abs($year - $p['year']) > 1) {
                continue;
            }
            if ($score >= $min) {
                $hit = $r + ['_year' => $year];
                break;
            }
        }
        if (!$hit) {
            return null;
        }

        $base = [
            'source' => 'tmdb',
            'source_id' => (int) $hit['id'],
            'type' => $p['type'],
            'tmdb_id' => (int) $hit['id'],
            'imdb_id' => null,
            'tvdb_id' => null,
            'title' => (string) ($hit[$isTv ? 'name' : 'title'] ?? $p['title']),
            'year' => $hit['_year'],
            'overview' => $hit['overview'] ?? null,
            'rating' => isset($hit['vote_average']) && $hit['vote_average'] > 0 ? round($hit['vote_average'], 1) : null,
            'poster_url' => !empty($hit['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $hit['poster_path'] : null,
        ];
        // Details (IDs, Genre, Regie, Darsteller) in einer Anfrage nachladen
        return array_merge($base, array_filter($this->details($p['type'], (int) $hit['id']) ?? [], fn ($v) => $v !== null));
    }

    public function details(string $type, int $sourceId): ?array
    {
        $isTv = $type === 'tv';
        $d = $this->get(($isTv ? '/tv/' : '/movie/') . $sourceId, ['append_to_response' => 'credits,external_ids']);
        if (!$d || empty($d['id'])) {
            return null;
        }
        $ext = $d['external_ids'] ?? [];
        $crew = $d['credits']['crew'] ?? [];
        $cast = $d['credits']['cast'] ?? [];

        $director = $isTv
            ? array_column($d['created_by'] ?? [], 'name')
            : array_column(array_filter($crew, fn ($c) => ($c['job'] ?? '') === 'Director'), 'name');
        $actors = array_column(array_slice($cast, 0, 8), 'name');
        $genres = array_column($d['genres'] ?? [], 'name');

        return [
            'imdb_id' => ($ext['imdb_id'] ?? $d['imdb_id'] ?? null) ?: null,
            'tvdb_id' => !empty($ext['tvdb_id']) ? (int) $ext['tvdb_id'] : null,
            'genres' => $genres ? mb_substr(implode(', ', $genres), 0, 255) : null,
            'director' => $director ? mb_substr(implode(', ', array_slice($director, 0, 3)), 0, 255) : null,
            'actors' => $actors ? implode(', ', $actors) : null,
            'overview' => ($d['overview'] ?? '') !== '' ? $d['overview'] : null,
            'rating' => isset($d['vote_average']) && $d['vote_average'] > 0 ? round($d['vote_average'], 1) : null,
            'poster_url' => !empty($d['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $d['poster_path'] : null,
        ];
    }

    private function request(): PendingRequest
    {
        $key = (string) config('indexer.tmdb.key');
        $req = Http::baseUrl(self::BASE)->timeout(15)->acceptJson();
        return str_starts_with($key, 'eyJ') ? $req->withToken($key) : $req->withQueryParameters(['api_key' => $key]);
    }

    private function get(string $path, array $params = []): array
    {
        try {
            $res = $this->request()->get($path, $params + ['language' => config('indexer.tmdb.language')]);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('TMDB nicht erreichbar: ' . $e->getMessage());
        }
        if ($res->status() === 401) {
            throw new \RuntimeException('TMDB-Key ungültig (401).');
        }
        if ($res->status() === 429 || $res->serverError()) {
            throw new \RuntimeException('TMDB-Limit oder Serverfehler (' . $res->status() . '), später erneut versuchen.');
        }
        return $res->successful() ? (array) $res->json() : [];
    }
}
