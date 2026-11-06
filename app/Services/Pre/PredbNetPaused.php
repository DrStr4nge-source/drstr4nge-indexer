<?php
namespace App\Services\Pre;

/** predb.net hat 403/429 geantwortet: Abfragen pausieren (einmal warnen, nicht alle 5 Minuten) */
class PredbNetPaused extends \RuntimeException
{
}
