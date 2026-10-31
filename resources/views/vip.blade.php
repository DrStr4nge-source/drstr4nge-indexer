<x-app-layout>
    <x-slot name="title">VIP</x-slot>
    <x-slot name="header">
        <div>
            <div class="tm-eyebrow">Mitgliedschaft</div>
            <h1 class="tm-h1">VIP</h1>
        </div>
        @if ($user->isVip())
            <span class="tm-pill is-vip">VIP bis {{ $user->vip_until->format('d.m.Y') }}</span>
        @endif
    </x-slot>

    @if (request('paid'))
        <div class="tm-alert ok">Danke! Sobald die Zahlung bestätigt ist, wird VIP automatisch freigeschaltet. Das kann je nach Kryptowährung einige Minuten dauern.</div>
    @endif
    @if (session('error'))
        <div class="tm-alert bad">{{ session('error') }}</div>
    @endif

    @unless ($ready)
        <div class="tm-alert warn">
            <span>Die Krypto-Zahlung ist noch nicht eingerichtet, VIP kann gerade nicht gekauft werden.
                @if ($user->is_admin)
                    <br><span class="tm-small tm-muted">Admin-Hinweis: Zahlungsart ist <code>{{ $provider }}</code>. In der <code>.env</code> fehlen
                    @if ($provider === 'nowpayments') <code>NOWPAYMENTS_API_KEY</code> oder <code>NOWPAYMENTS_IPN_SECRET</code>.
                    @else <code>BTCPAY_URL</code>, <code>BTCPAY_STORE_ID</code> oder <code>BTCPAY_API_KEY</code>.
                    @endif
                    Danach <code>php artisan config:clear</code>.</span>
                @endif
            </span>
        </div>
    @endunless

    <section class="tm-panel">
        <p class="tm-muted" style="margin-bottom:18px">
            @if ($user->isVip()) Ein Kauf verlängert deine VIP-Zeit.
            @else Mit VIP gibt es keine Download-Limits und ein höheres API-Limit. Bezahlung per Krypto{{ $provider === 'nowpayments' ? ' (z.B. Monero, Bitcoin, Litecoin)' : '' }}.
            @endif
        </p>
        <div class="tm-grid cols-3">
            @foreach ($plans as $key => $p)
                <form method="POST" action="{{ route('vip.buy', $key) }}" class="tm-plan">@csrf
                    <div class="tm-eyebrow" style="margin:0">{{ $p['label'] }}</div>
                    <div class="price">{{ $p['price'] }} <small>{{ config('indexer.currency') }}</small></div>
                    <div class="tm-muted tm-small">{{ number_format($p['price'] / max(1, $p['months']), 2, ',', '.') }} {{ config('indexer.currency') }} pro Monat</div>
                    <button class="tm-btn tm-btn-primary" style="margin-top:8px;width:100%" @disabled(!$ready)>Kaufen</button>
                </form>
            @endforeach
        </div>
    </section>
</x-app-layout>
