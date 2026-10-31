<?php
namespace App\Services;

/**
 * Findet per binärer Suche den ersten Artikel einer Gruppe, der nicht älter als ein Stichtag ist.
 * Reine PHP-Klasse; die Abfrage eines Artikeldatums wird als Funktion übergeben (testbar ohne Server).
 */
class Backfill
{
    /**
     * @param callable(int):?array{0:int,1:int} $probe liefert [Artikelnummer, Zeitstempel] des ersten vorhandenen
     *        Artikels ab Nummer n (in einem kleinen Fenster) oder null, wenn dort nichts lesbar ist
     * @return int Artikelnummer, ab der gelesen werden soll (lieber etwas zu früh als zu spät)
     */
    public static function findStart(callable $probe, int $low, int $high, int $cutoff, int $precision = 200): int
    {
        if ($high <= $low) {
            return $low;
        }
        $first = $probe($low);
        if ($first !== null && $first[1] >= $cutoff) {
            return $low; // die ganze Gruppe ist jünger als der Stichtag
        }
        $lo = $low;
        $hi = $high;
        for ($i = 0; $i < 64 && $hi - $lo > $precision; $i++) {
            $mid = intdiv($lo + $hi, 2);
            $r = $probe($mid);
            if ($r === null || $r[1] >= $cutoff) {
                $hi = $mid; // jung genug (oder unklar): weiter vorne suchen, damit nichts fehlt
            } else {
                $lo = max($mid, $r[0]); // zu alt: weiter hinten suchen
            }
        }
        return max($low, $lo);
    }
}
