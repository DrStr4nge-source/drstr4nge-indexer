<x-app-layout>
    <x-slot name="title">Releases</x-slot>
    <x-slot name="header">
        <div>
            <div class="tm-eyebrow">Suche</div>
            <h1 class="tm-h1">
                @if (request('q')) Ergebnisse für „{{ request('q') }}“
                @elseif (request('cat')) {{ $parents[request('cat')] ?? 'Releases' }}
                @else Alle Releases
                @endif
            </h1>
        </div>
        <div class="tm-muted">{{ number_format($releases->total(), 0, ',', '.') }} Treffer</div>
    </x-slot>

    <section class="tm-panel">
        <form method="GET" class="tm-form-row" style="margin-bottom:16px">
            <input class="tm-input grow" type="search" name="q" value="{{ request('q') }}" placeholder="Name, Gruppe, Auflösung …">
            <select name="cat" class="tm-select" style="width:auto">
                <option value="">Alle Kategorien</option>
                @foreach ($parents as $id => $label)
                    <option value="{{ $id }}" @selected(request('cat') == $id)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="tm-btn tm-btn-primary">Suchen</button>
        </form>
        <x-release-table :releases="$releases" />
        {{ $releases->links() }}
    </section>
</x-app-layout>
