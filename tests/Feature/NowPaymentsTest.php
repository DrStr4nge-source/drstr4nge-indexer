<?php
namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\NowPaymentsSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NowPaymentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'https://indexer.example.com',
            'indexer.payment_provider' => 'nowpayments',
            'indexer.nowpayments' => ['api_key' => 'KEY', 'ipn_secret' => 'IPN', 'pay_currency' => 'xmr', 'sandbox' => false, 'paid_statuses' => ['finished']],
        ]);
    }

    private function ipn(array $data, ?string $secret = 'IPN')
    {
        $raw = json_encode($data);
        $sig = $secret ? NowPaymentsSignature::sign($data, $secret) : 'falsch';
        return $this->call('POST', '/nowpayments/ipn', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_NOWPAYMENTS_SIG' => $sig], $raw);
    }

    public function test_buy_creates_invoice_with_monero_and_redirects(): void
    {
        Http::fake(['api.nowpayments.io/v1/invoice' => Http::response(['id' => '4522625843', 'invoice_url' => 'https://nowpayments.io/payment/?iid=4522625843'])]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/vip/buy/3m')->assertRedirect('https://nowpayments.io/payment/?iid=4522625843');
        $p = Payment::first();
        $this->assertSame('np-4522625843', $p->invoice_id);
        Http::assertSent(fn ($r) => $r->hasHeader('x-api-key', 'KEY') && $r['pay_currency'] === 'xmr' && $r['price_currency'] === 'eur'
            && (float) $r['price_amount'] === 12.0 && $r['order_id'] === (string) $p->id && $r['ipn_callback_url'] === 'https://indexer.example.com/nowpayments/ipn');
    }

    public function test_failed_invoice_leaves_no_payment(): void
    {
        Http::fake(['api.nowpayments.io/*' => Http::response(['message' => 'Invalid api key'], 403)]);
        $this->actingAs(User::factory()->create())->post('/vip/buy/1m')->assertRedirect(route('vip'))->assertSessionHas('error');
        $this->assertSame(0, Payment::count());
    }

    public function test_ipn_finished_extends_vip_once(): void
    {
        $user = User::factory()->create();
        $p = Payment::create(['user_id' => $user->id, 'invoice_id' => 'np-777', 'plan' => '1m', 'amount' => 5, 'currency' => 'EUR']);

        $this->ipn(['order_id' => (string) $p->id, 'invoice_id' => 777, 'payment_status' => 'confirming'])->assertOk();
        $this->assertFalse($user->fresh()->isVip(), 'erst bei "finished"');

        $this->ipn(['order_id' => (string) $p->id, 'invoice_id' => 777, 'payment_status' => 'finished'])->assertOk();
        $vip = $user->fresh()->vip_until;
        $this->assertTrue($user->fresh()->isVip());
        $this->assertSame('paid', $p->fresh()->status);

        $this->ipn(['order_id' => (string) $p->id, 'invoice_id' => 777, 'payment_status' => 'finished'])->assertOk();
        $this->assertTrue($vip->equalTo($user->fresh()->vip_until), 'doppelte Meldung verlängert nicht nochmal');
    }

    public function test_ipn_rejects_bad_signature_and_mismatches(): void
    {
        $user = User::factory()->create();
        $p = Payment::create(['user_id' => $user->id, 'invoice_id' => 'np-777', 'plan' => '1m', 'amount' => 5, 'currency' => 'EUR']);

        $this->ipn(['order_id' => (string) $p->id, 'payment_status' => 'finished'], null)->assertForbidden();
        $this->ipn(['order_id' => (string) $p->id, 'invoice_id' => 999, 'payment_status' => 'finished'])->assertOk();
        $this->assertFalse($user->fresh()->isVip(), 'fremde Rechnungsnummer');

        $btc = Payment::create(['user_id' => $user->id, 'invoice_id' => 'BTCPAY123', 'plan' => '1m', 'amount' => 5, 'currency' => 'EUR']);
        $this->ipn(['order_id' => (string) $btc->id, 'payment_status' => 'finished'])->assertOk();
        $this->assertFalse($user->fresh()->isVip(), 'BTCPay-Zahlung darf nicht per NOWPayments bezahlt werden');

        $this->ipn(['order_id' => (string) $p->id, 'invoice_id' => 777, 'payment_status' => 'partially_paid'])->assertOk();
        $this->assertFalse($user->fresh()->isVip(), 'Teilzahlung');

        $this->ipn(['order_id' => (string) $p->id, 'invoice_id' => 777, 'payment_status' => 'expired'])->assertOk();
        $this->assertSame('expired', $p->fresh()->status);
    }

    public function test_vip_page_shows_hint_when_not_configured(): void
    {
        config(['indexer.nowpayments.api_key' => null]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();
        $this->actingAs($admin)->get('/vip')->assertOk()->assertSee('NOWPAYMENTS_API_KEY');
    }
}
