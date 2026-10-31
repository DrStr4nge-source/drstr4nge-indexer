<?php

namespace Tests\Unit;

use App\Support\QrCode;
use App\Support\Totp;
use PHPUnit\Framework\TestCase;

class TwoFactorTest extends TestCase
{
    public function test_rfc6238_testwerte(): void
    {
        $secret = Totp::base32('12345678901234567890'); // Testschlüssel aus RFC 6238
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret);
        foreach ([59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'] as $t => $code) {
            $this->assertSame($code, Totp::code($secret, $t, 8), "Zeit $t");
        }
        $this->assertSame('287082', Totp::code($secret, 59));
    }

    public function test_pruefen_mit_toleranz_und_ohne_wiederholung(): void
    {
        $s = Totp::secret();
        $this->assertSame(32, strlen($s));
        $t = 1_800_000_015;
        $step = Totp::verify($s, Totp::code($s, $t), null, $t);
        $this->assertSame(intdiv($t, 30), $step);
        $this->assertSame(intdiv($t, 30) - 1, Totp::verify($s, Totp::code($s, $t - 30), null, $t)); // 30 s alt: noch gültig
        $this->assertNull(Totp::verify($s, Totp::code($s, $t - 90), null, $t));                       // zu alt
        $this->assertNull(Totp::verify($s, Totp::code($s, $t), $step, $t));                            // schon benutzt
        $this->assertNull(Totp::verify($s, 'abc123', null, $t));
        $this->assertSame(intdiv($t, 30), Totp::verify($s, substr(Totp::code($s, $t), 0, 3) . ' ' . substr(Totp::code($s, $t), 3), null, $t)); // "123 456"
    }

    public function test_uri_und_qr(): void
    {
        $u = Totp::uri('ABC', 'admin@example.com', 'Mein Indéx');
        $this->assertStringContainsString('otpauth://totp/Mein%20Ind%C3%A9x:admin%40example.com?secret=ABC&issuer=Mein%20Ind%C3%A9x', $u);
        $svg = QrCode::svg($u);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertCount(49, QrCode::matrix($u)); // Version 8
    }
}
