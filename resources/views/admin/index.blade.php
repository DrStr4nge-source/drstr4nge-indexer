<x-app-layout>
    <x-slot name="title">Admin</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Admin</h1><span class="tm-chip">DrStr4nge Indexer v{{ \App\Support\Brand::version() }}</span></x-slot>
    <div>
        @include('admin._nav')
        <div class="tm-grid cols-6">
            @foreach (['User' => $users, 'VIPs' => $vips, 'Releases' => number_format($releases, 0, ',', '.'), 'Aktive Gruppen' => $groups, 'Downloads heute' => $grabs, 'Einnahmen' => number_format($income, 2, ',', '.') . ' ' . config('indexer.currency')] as $label => $v)
                <div class="tm-stat"><div class="tm-muted tm-small">{{ $label }}</div><div class="v">{{ $v }}</div></div>
            @endforeach
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
