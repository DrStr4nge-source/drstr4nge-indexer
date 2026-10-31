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
    </div>
</x-app-layout>
