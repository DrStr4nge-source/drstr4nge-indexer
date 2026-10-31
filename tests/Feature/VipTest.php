<?php
namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VipTest extends TestCase
{
    use RefreshDatabase;

    private function configure(): void
    {
        config(['indexer.btcpay' => ['url' => 'https://pay.example.com', 'store' => 'STORE1', 'key' => 'KEY1', 'webhook_secret' => 's']]);
    }

    public function test_page_works_without_btcpay_and_shows_hint(): void
    {
        config(['indexer.btcpay' => ['url' => null, 'store' => null, 'key' => null, 'webhook_secret' => null]]);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/vip')->assertOk()->assertSee('noch nicht eingerichtet')->assertDontSee('Admin-Hinweis');

        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user)->get('/vip')->assertOk()->assertSee('Admin-Hinweis');
    }

    public function test_buy_without_btcpay_redirects_with_message_instead_of_500(): void
    {
        config(['indexer.btcpay' => ['url' => null, 'store' => null, 'key' => null, 'webhook_secret' => null]]);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/vip/buy/1m')
            ->assertRedirect(route('vip'))->assertSessionHas('error');
        $this->assertSame(0, Payment::count());
    }

    public function test_buy_creates_invoice_and_redirects_to_checkout(): void
    {
        $this->configure();
        Http::fake(['pay.example.com/*' => Http::response(['id' => 'INV9', 'checkoutLink' => 'https://pay.example.com/i/INV9'])]);
        $user = User::factory()->create();

        $this->actingAs($user)->post('/vip/buy/3m')->assertRedirect('https://pay.example.com/i/INV9');
        $this->assertSame('INV9', Payment::first()->invoice_id);
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'token KEY1')
            && str_ends_with($r->url(), '/api/v1/stores/STORE1/invoices')
            && $r['amount'] === '12');
    }

    public function test_btcpay_error_shows_message(): void
    {
        $this->configure();
        Http::fake(['pay.example.com/*' => Http::response(['message' => 'Unauthorized'], 401)]);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/vip/buy/1m')->assertRedirect(route('vip'))->assertSessionHas('error');
        $this->assertSame(0, Payment::count());
    }

    public function test_btcpay_unreachable_shows_message(): void
    {
        $this->configure();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));
        $user = User::factory()->create();
        $this->actingAs($user)->post('/vip/buy/1m')->assertRedirect(route('vip'))->assertSessionHas('error');
    }

    public function test_unknown_plan_is_404(): void
    {
        $this->configure();
        $this->actingAs(User::factory()->create())->post('/vip/buy/99y')->assertNotFound();
    }
}
