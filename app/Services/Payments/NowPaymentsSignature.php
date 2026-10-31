<?php
namespace App\Services\Payments;

/** Prüft die Signatur der NOWPayments-Rückmeldung (IPN). Reine PHP-Klasse. */
class NowPaymentsSignature
{
    /** HMAC-SHA512 über das JSON mit rekursiv alphabetisch sortierten Schlüsseln. */
    public static function valid(string $rawBody, ?string $signature, string $secret): bool
    {
        $secret = trim($secret);
        if ($secret === '' || !$signature) {
            return false;
        }
        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            return false;
        }
        self::sortRecursive($data);
        $sig = strtolower(trim($signature));
        // Ohne und mit Unicode-Escapes vergleichen (je nachdem, wie der Absender serialisiert)
        foreach ([JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE] as $flags) {
            if (hash_equals(hash_hmac('sha512', (string) json_encode($data, $flags), $secret), $sig)) {
                return true;
            }
        }
        return false;
    }

    public static function sign(array $data, string $secret): string
    {
        self::sortRecursive($data);
        return hash_hmac('sha512', (string) json_encode($data, JSON_UNESCAPED_SLASHES), trim($secret));
    }

    private static function sortRecursive(array &$a): void
    {
        ksort($a, SORT_STRING);
        foreach ($a as &$v) {
            if (is_array($v)) {
                self::sortRecursive($v);
            }
        }
    }
}
