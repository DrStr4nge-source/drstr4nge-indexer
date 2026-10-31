<?php
namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** VIP-Kauf über NOWPayments (gehostet, u.a. Monero). */
class NowPayments
{
    public static function ready(): bool
    {
        $c = config('indexer.nowpayments');
        return !empty($c['api_key']) && !empty($c['ipn_secret']);
    }

    /** Legt eine Rechnung an und liefert die Bezahl-Adresse, oder null bei Fehler. */
    public static function createInvoice(Payment $payment, array $plan): ?string
    {
        $c = config('indexer.nowpayments');
        $base = $c['sandbox'] ? 'https://api-sandbox.nowpayments.io/v1' : 'https://api.nowpayments.io/v1';
        $body = array_filter([
            'price_amount' => (float) $plan['price'],
            'price_currency' => strtolower((string) config('indexer.currency')),
            'pay_currency' => $c['pay_currency'] ?: null, // leer = Kunde wählt die Kryptowährung
            'order_id' => (string) $payment->id,
            'order_description' => 'VIP ' . $plan['label'],
            // fest aus APP_URL, nicht aus der aufgerufenen Adresse (die kann eine interne IP sein)
            'ipn_callback_url' => rtrim((string) config('app.url'), '/') . '/nowpayments/ipn',
            'success_url' => route('vip', ['paid' => 1]),
            'cancel_url' => route('vip'),
        ], fn ($v) => $v !== null && $v !== '');

        try {
            $res = Http::timeout(15)->withHeaders(['x-api-key' => $c['api_key']])->acceptJson()->post($base . '/invoice', $body);
        } catch (ConnectionException $e) {
            Log::error('NOWPayments nicht erreichbar: ' . $e->getMessage());
            return null;
        }
        if (!$res->successful() || empty($res['id']) || empty($res['invoice_url'])) {
            Log::error('NOWPayments-Rechnung fehlgeschlagen', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);
            return null;
        }
        $payment->update(['invoice_id' => 'np-' . $res['id']]);
        return (string) $res['invoice_url'];
    }
}
