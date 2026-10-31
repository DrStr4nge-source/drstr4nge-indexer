<x-app-layout>
    <x-slot name="title">Admin</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Admin</h1><span class="tm-chip">DrStr4nge Indexer v{{ \App\Support\Brand::version() }}</span></x-slot>
    <div>
        @include('admin._nav')
        @if ($problems)
            <div class="tm-alert bad" style="display:block;margin-bottom:16px">
                <div style="font-weight:600;margin-bottom:4px">Probleme</div>
                @foreach ($problems as $p)<div class="tm-small" style="margin-top:4px">{{ $p }}</div>@endforeach
                <div class="tm-small" style="margin-top:8px"><a class="tm-link" href="{{ route('admin.log') }}">Zum Log</a></div>
            </div>
        @endif
        @if ($no2fa)
            <div class="tm-alert info" style="margin-bottom:16px"><span>Tipp: Schütze den Admin-Bereich mit einem zweiten Faktor (Code-App auf dem Handy).</span><a class="tm-btn tm-btn-sm" href="{{ route('admin.2fa') }}">Einrichten</a></div>
        @endif
        <div class="tm-grid cols-6">
            @foreach (['User' => $users, 'VIPs' => $vips, 'Releases' => number_format($releases, 0, ',', '.'), 'Aktive Gruppen' => $groups, 'Downloads heute' => $grabs, 'Einnahmen' => number_format($income, 2, ',', '.') . ' ' . config('indexer.currency')] as $label => $v)
                <div class="tm-stat"><div class="tm-muted tm-small">{{ $label }}</div><div class="v">{{ $v }}</div></div>
            @endforeach
        </div>

        <h2 class="tm-h2" style="margin:28px 0 12px">Betrieb</h2>
        @php($tmAgo = fn ($t) => $t ? \App\Support\Format::ago(\Illuminate\Support\Carbon::createFromTimestamp($t)) : 'noch nie')
        <div class="tm-grid cols-4">
            <div class="tm-stat"><div class="tm-muted tm-small">Crawler</div><div class="v" style="font-size:20px;padding:4px 0">{{ $ops['running'] ? 'läuft' : 'wartet' }}</div><div class="tm-muted tm-small">letzter Lauf {{ $tmAgo($ops['crawl']) }}</div></div>
            <div class="tm-stat"><div class="tm-muted tm-small">Sicherung</div><div class="v" style="font-size:20px;padding:4px 0;{{ $ops['backupError'] ? 'color:var(--tm-bad)' : '' }}">{{ $ops['backupError'] ? 'Fehler' : $tmAgo($ops['backup']) }}</div><div class="tm-muted tm-small"><a class="tm-link" href="{{ route('admin.credentials') }}#backup">Einstellungen</a></div></div>
            <div class="tm-stat"><div class="tm-muted tm-small">Pre-Daten</div><div class="v" style="font-size:20px;padding:4px 0;color:{{ $ops['irc'] ? 'var(--tm-ok)' : 'var(--tm-muted)' }}">{{ $ops['irc'] ? 'IRC verbunden' : 'IRC getrennt' }}</div><div class="tm-muted tm-small">{{ number_format($ops['pres'], 0, ',', '.') }} Pres heute</div></div>
            <div class="tm-stat"><div class="tm-muted tm-small">Meldungen</div><div class="v">{{ $ops['reports'] }}</div><div class="tm-muted tm-small"><a class="tm-link" href="{{ route('admin.reports') }}">offen</a></div></div>
        </div>

        <h2 class="tm-h2" style="margin:28px 0 12px">Server</h2>
        <div id="tm-system" data-url="{{ route('admin.system') }}">
            @include('admin._system')
        </div>
    </div>
    <script>
    (function () {
        var box = document.getElementById('tm-system');
        if (!box || !window.fetch) return;
        function refresh() {
            if (document.hidden) return;
            fetch(box.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.text() : null; })
                .then(function (html) { if (html) box.innerHTML = html; })
                .catch(function () {});
        }
        setInterval(refresh, 5000);
    })();
    </script>
</x-app-layout>
