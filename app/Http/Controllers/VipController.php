<?php
namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\Payments\NowPayments;
use App\Services\Payments\NowPaymentsSignature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** VIP-Kauf per Krypto über BTCPay Server oder NOWPayments (PAYMENT_PROVIDER in der .env). */
class VipController extends Controller
{
    public static function provider(): string
    {
        return config('indexer.payment_provider') === 'nowpayments' ? 'nowpayments' : 'btcpay';
    }

    /** Ist die gewählte Zahlungsart vollständig eingerichtet? */
    public static function paymentReady(): bool
    {
        if (self::provider() === 'nowpayments') {
            return NowPayments::ready();
        }
        $c = config('indexer.btcpay');
        return !empty($c['url']) && !empty($c['store']) && !empty($c['key']);
    }

    public function index(Request $request)
    {
        return view('vip', [
            'plans' => config('indexer.plans'),
            'user' => $request->user(),
            'ready' => self::paymentReady(),
            'provider' => self::provider(),
        ]);
    }

    public function buy(Request $request, string $plan)
    {
        $p = ((array) config('indexer.plans'))[$plan] ?? null; // kein config("...$plan"): Punkte im Namen würden tiefer greifen
        abort_unless(is_array($p) && isset($p['price']), 404);

        if (!self::paymentReady()) {
            return redirect()->route('vip')->with('error', 'Die Krypto-Zahlung ist noch nicht eingerichtet. Bitte später erneut versuchen.');
        }
        $fail = fn () => redirect()->route('vip')->with('error', 'Die Rechnung konnte gerade nicht erstellt werden. Bitte später erneut versuchen.');

        if (self::provider() === 'nowpayments') {
            $payment = Payment::create([
                'user_id' => $request->user()->id,
                'invoice_id' => 'np-pending-' . bin2hex(random_bytes(8)),
                'plan' => $plan,
                'amount' => $p['price'],
                'currency' => config('indexer.currency'),
            ]);
            $url = NowPayments::createInvoice($payment, $p);
            if (!$url) {
                $payment->delete();
                return $fail();
            }
            return redirect()->away($url);
        }

        $c = config('indexer.btcpay');
        try {
            $res = Http::timeout(15)
                ->withHeaders(['Authorization' => 'token ' . $c['key']])
                ->post(rtrim($c['url'], '/') . "/api/v1/stores/{$c['store']}/invoices", [
                    'amount' => (string) $p['price'],
                    'currency' => config('indexer.currency'),
                    'metadata' => ['userId' => $request->user()->id, 'plan' => $plan],
                    'checkout' => ['redirectURL' => route('vip')],
                ]);
        } catch (ConnectionException $e) {
            Log::error('BTCPay nicht erreichbar: ' . $e->getMessage());
            return $fail();
        }

        if (!$res->successful() || empty($res['id']) || empty($res['checkoutLink'])) {
            Log::error('BTCPay-Rechnung fehlgeschlagen', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);
            return $fail();
        }

        Payment::create([
            'user_id' => $request->user()->id,
            'invoice_id' => $res['id'],
            'plan' => $plan,
            'amount' => $p['price'],
            'currency' => config('indexer.currency'),
        ]);
        return redirect()->away($res['checkoutLink']);
    }

    /** Rückmeldung von NOWPayments (IPN): bei Status "finished" VIP verlängern. */
    public function nowpaymentsIpn(Request $request)
    {
        $raw = $request->getContent();
        if (!NowPaymentsSignature::valid($raw, $request->header('x-nowpayments-sig'), (string) config('indexer.nowpayments.ipn_secret'))) {
            Log::warning('NOWPayments-IPN mit ungültiger Signatur abgelehnt.');
            abort(403);
        }
        $data = json_decode($raw, true);
        $payment = ctype_digit((string) ($data['order_id'] ?? '')) ? Payment::find((int) $data['order_id']) : null;
        if (!$payment || !str_starts_with((string) $payment->invoice_id, 'np-')) {
            return response('unknown order', 200); // nicht erneut senden lassen
        }
        // Rechnungsnummer muss passen, falls mitgeschickt
        if (!empty($data['invoice_id']) && $payment->invoice_id !== 'np-' . $data['invoice_id']) {
            Log::warning('NOWPayments-IPN: Rechnungsnummer passt nicht', ['order' => $payment->id]);
            return response('mismatch', 200);
        }
        $status = (string) ($data['payment_status'] ?? '');
        $paidStatuses = (array) config('indexer.nowpayments.paid_statuses', ['finished']);
        if (in_array($status, $paidStatuses, true)) {
            $payment->markPaid();
        } elseif (in_array($status, ['failed', 'expired', 'refunded'], true) && $payment->status !== 'paid') {
            $payment->update(['status' => $status]);
        }
        return response('ok');
    }

    /** Webhook von BTCPay: "InvoiceSettled" => VIP verlängern. */
    public function webhook(Request $request)
    {
        $secret = (string) config('indexer.btcpay.webhook_secret');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
        abort_unless($secret !== '' && hash_equals($expected, (string) $request->header('BTCPay-Sig')), 403);

        if ($request->input('type') === 'InvoiceSettled') {
            Payment::where('invoice_id', $request->input('invoiceId'))->first()?->markPaid();
        }
        return response('ok');
    }
}
