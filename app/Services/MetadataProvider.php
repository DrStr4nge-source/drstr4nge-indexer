<?php
namespace App\Services;

/**
 * Quelle für Film- und Serien-Metadaten (z.B. TMDB). Neue Quelle = neue Klasse mit diesen drei Methoden,
 * eintragen in config/indexer.php unter metadata.providers, aktivieren mit METADATA_PROVIDER in der .env.
 */
interface MetadataProvider
{
    /** Kurzname, wird für Cache und Cover-Dateinamen genutzt (z.B. "tmdb"). */
    public function name(): string;

    /** false, wenn die Quelle nicht nutzbar ist (z.B. API-Key fehlt). */
    public function enabled(): bool;

    /**
     * @param array{type:string,title:string,year:?int} $parsed Ergebnis von TitleParser::parse() (type: movie|tv)
     * @return array|null null = kein sicherer Treffer. Pflicht: source, source_id, type, title.
     *                    Optional: year, overview, rating, poster_url (volle URL), imdb_id, tmdb_id, tvdb_id.
     * @throws \RuntimeException bei Netzwerk-, Key- oder Limit-Problemen (dann wird später erneut versucht)
     */
    public function find(array $parsed): ?array;
}
