<?php
namespace App\Services;

/** Platzhalter, wenn keine Metadaten-Quelle aktiv ist. */
class NullProvider implements MetadataProvider
{
    public function name(): string
    {
        return 'none';
    }

    public function enabled(): bool
    {
        return false;
    }

    public function find(array $parsed): ?array
    {
        return null;
    }
}
