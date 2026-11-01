<?php
namespace App\Support;

/** Text sicher für XML (Newznab-API, RSS, NZB): maskiert & < > " ' und entfernt in XML verbotene Steuerzeichen. */
class Xml
{
    public static function esc(mixed $s): string
    {
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
