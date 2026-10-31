<?php
namespace App\Services;

/** Der Usenet-Server hat die Verbindung getrennt oder antwortet nicht mehr. Neu verbinden hilft meistens. */
class NntpConnectionLost extends \RuntimeException
{
}
