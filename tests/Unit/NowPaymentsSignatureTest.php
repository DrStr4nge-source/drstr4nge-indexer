<?php
namespace Tests\Unit;

use App\Services\Payments\NowPaymentsSignature;
use PHPUnit\Framework\TestCase;

class NowPaymentsSignatureTest extends TestCase
{
    public function test_valid_signature_with_nested_unsorted_payload(): void
    {
        $payload = ['payment_status' => 'finished', 'order_id' => '42', 'fee' => ['serviceFee' => 0.0004, 'currency' => 'xmr'], 'actually_paid' => 0.07432];
        $raw = json_encode($payload, JSON_PRETTY_PRINT);
        // Erwartung unabhängig berechnet: Schlüssel sortiert, kompaktes JSON
        $canon = '{"actually_paid":0.07432,"fee":{"currency":"xmr","serviceFee":0.0004},"order_id":"42","payment_status":"finished"}';
        $sig = hash_hmac('sha512', $canon, 'secret');
        $this->assertTrue(NowPaymentsSignature::valid($raw, $sig, 'secret'));
        $this->assertTrue(NowPaymentsSignature::valid($raw, $sig, " secret\n"), 'Leerzeichen um das Secret');
        $this->assertFalse(NowPaymentsSignature::valid($raw, $sig, 'other'));
        $this->assertFalse(NowPaymentsSignature::valid(str_replace('finished', 'waiting', $raw), $sig, 'secret'));
        $this->assertFalse(NowPaymentsSignature::valid($raw, null, 'secret'));
        $this->assertFalse(NowPaymentsSignature::valid($raw, $sig, ''));
    }
}
