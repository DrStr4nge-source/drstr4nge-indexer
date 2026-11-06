<x-app-layout>
    <x-slot name="title">Releases</x-slot>
    <x-slot name="header">
        <div>
            <div class="tm-eyebrow">Suche</div>
            <h1 class="tm-h1">
                @if (request('q')) Ergebnisse für „{{ request('q') }}“
                @elseif (request('cat')) {{ $parents[request('cat')] ?? $catName ?? 'Releases' }}
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
                @foreach ($tree as $id => $t)
                    @if ($t['subs'])
                        <optgroup label="{{ $t['name'] }}">
                            <option value="{{ $id }}" @selected(request('cat') == $id)>{{ $t['name'] }} (alle)</option>
                            @foreach ($t['subs'] as $sid => $sname)
                                <option value="{{ $sid }}" @selected(request('cat') == $sid)>{{ $sname }}</option>
                            @endforeach
                        </optgroup>
                    @else
                        <option value="{{ $id }}" @selected(request('cat') == $id)>{{ $t['name'] }}</option>
                    @endif
                @endforeach
            </select>
            <button class="tm-btn tm-btn-primary">Suchen</button>
        </form>
        <x-release-table :releases="$releases" />
        {{ $releases->links() }}
    </section>
</x-app-layout>
