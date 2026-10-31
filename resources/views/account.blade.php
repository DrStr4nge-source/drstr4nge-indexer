<x-app-layout>
    <x-slot name="title">Mein Konto</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Mein Konto</h1></x-slot>

    <div class="tm-grid cols-2">
        <section class="tm-panel">
            <div class="tm-eyebrow">Status</div>
            @if ($user->isVip())
                <h2 class="tm-h2" style="margin-bottom:10px">VIP bis {{ $user->vip_until->format('d.m.Y') }}</h2>
                <p class="tm-muted">Unbegrenzte Downloads und höheres API-Limit.</p>
            @else
                @php($tmMax = max(1, (int) config('indexer.free_grabs_per_day')))
                @php($tmUsed = $user->grabsToday())
                <h2 class="tm-h2" style="margin-bottom:10px">Standard</h2>
                <p class="tm-muted" style="margin-bottom:8px">{{ $tmUsed }} von {{ $tmMax }} Downloads heute genutzt.</p>
                <div class="tm-bar"><span style="width:{{ min(100, round($tmUsed / $tmMax * 100)) }}%"></span></div>
            @endif
            <div style="margin-top:16px"><a href="{{ route('vip') }}" class="tm-btn tm-btn-primary">VIP {{ $user->isVip() ? 'verlängern' : 'holen' }}</a></div>
        </section>

        <section class="tm-panel">
            <div class="tm-eyebrow">Für Sonarr, Radarr und Co.</div>
            <h2 class="tm-h2" style="margin-bottom:10px">API-Key</h2>
            <div class="tm-form-row" style="margin-bottom:10px">
                <input class="tm-input grow" value="{{ $user->api_key }}" readonly onfocus="this.select()">
                <button type="button" class="tm-btn" data-copy="{{ $user->api_key }}">Kopieren</button>
            </div>
            <p class="tm-muted tm-small" style="margin-bottom:12px">Adresse: <code>{{ url('/') }}</code> · Typ: Newznab</p>
            <form method="POST" action="{{ route('key.regenerate') }}" onsubmit="return confirm('Neuen Key erzeugen? Der alte funktioniert dann nicht mehr.')">@csrf
                <button class="tm-btn tm-btn-sm">Neuen Key erzeugen</button>
            </form>
        </section>
    </div>

    <section class="tm-panel">
        <div class="tm-eyebrow">Verlauf</div>
        <h2 class="tm-h2" style="margin-bottom:12px">Letzte Downloads</h2>
        <div class="tm-list">
            @forelse ($recent as $d)
                <div>
                    <span class="tm-relname">{{ $d->name }}</span>
                    <span class="tm-muted tm-small" style="white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($d->created_at)->format('d.m.Y H:i') }}</span>
                </div>
            @empty
                <div class="tm-muted">Noch keine Downloads.</div>
            @endforelse
        </div>
    </section>
</x-app-layout>
