<?php
namespace App\Services;

/** Liefert den in der Config gewählten Metadaten-Anbieter. */
class Metadata
{
    public static function provider(): MetadataProvider
    {
        $name = (string) config('indexer.metadata.provider');
        $class = config("indexer.metadata.providers.$name");
        if (is_string($class) && is_a($class, MetadataProvider::class, true)) {
            return app($class);
        }
        return new NullProvider();
    }
}
