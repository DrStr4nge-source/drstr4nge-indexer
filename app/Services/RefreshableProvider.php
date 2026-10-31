<?php
namespace App\Services;

/** Optional: Anbieter, der zu einem bekannten Titel Details (Genre, Regie, Darsteller) nachladen kann. */
interface RefreshableProvider
{
    /**
     * @return array|null Felder wie bei find(): genres, director, actors, imdb_id, tvdb_id, overview, rating, poster_url
     * @throws \RuntimeException bei Netzwerk-, Key- oder Limit-Problemen
     */
    public function details(string $type, int $sourceId): ?array;
}
