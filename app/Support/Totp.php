<?php
namespace App\Support;

/** Einmal-Codes nach RFC 6238 (30 Sekunden, 6 Ziffern, SHA1), kompatibel mit Google Authenticator, Aegis, 2FAS usw. */
class Totp
{
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function secret(int $bytes = 20): string
    {
        return self::base32(random_bytes($bytes));
    }

    public static function code(string $secret, ?int $time = null, int $digits = 6): string
    {
        return self::at($secret, intdiv($time ?? time(), 30), $digits);
    }

    /**
     * Prüft einen Code (±1 Zeitschritt Toleranz). Gibt den Zeitschritt zurück oder null.
     * $lastStep: zuletzt benutzter Schritt, ältere/gleiche werden abgelehnt (kein zweimal derselbe Code).
     */
    public static function verify(string $secret, string $code, ?int $lastStep = null, ?int $time = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $now = intdiv($time ?? time(), 30);
        for ($s = $now - 1; $s <= $now + 1; $s++) {
            if (($lastStep === null || $s > $lastStep) && hash_equals(self::at($secret, $s), $code)) {
                return $s;
            }
        }
        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);
        return "otpauth://totp/$label?secret=$secret&issuer=" . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    private static function at(string $secret, int $step, int $digits = 6): string
    {
        $h = hash_hmac('sha1', pack('J', $step), self::unbase32($secret), true);
        $o = ord($h[19]) & 0x0F;
        $n = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
        return str_pad((string) ($n % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    public static function base32(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 5) as $chunk) $out .= self::B32[bindec(str_pad($chunk, 5, '0'))];
        return $out;
    }

    public static function unbase32(string $s): string
    {
        $s = strtoupper(preg_replace('/[\s=]/', '', $s));
        $bits = '';
        foreach (str_split($s) as $c) {
            $p = strpos(self::B32, $c);
            if ($p === false) throw new \InvalidArgumentException('Ungültiger Schlüssel');
            $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
        return $out;
    }
}
