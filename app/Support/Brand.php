<?php
namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/** Name und Logo der Seite (im Admin unter Einstellungen änderbar). */
class Brand
{
    public static function name(): string
    {
        return (string) Setting::get('site_name', config('app.name', 'DrStr4nge Indexer'));
    }

    public static function logoUrl(): ?string
    {
        $path = Setting::get('logo_path');
        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }

    public static function version(): string
    {
        return (string) config('indexer.version', '');
    }

    /** Fehlt die Verknüpfung public/storage, sind hochgeladene Bilder nicht erreichbar. */
    public static function storageLinked(): bool
    {
        return file_exists(public_path('storage'));
    }

    /** Name für das Text-Logo: letztes Wort hervorgehoben ("DrStr4nge <b>Indexer</b>"). */
    public static function nameParts(): array
    {
        $name = self::name();
        $pos = strrpos($name, ' ');
        return $pos === false ? ['', $name] : [substr($name, 0, $pos + 1), substr($name, $pos + 1)];
    }
}
