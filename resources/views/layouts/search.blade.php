@php($tmId = 's' . substr(md5(uniqid('', true)), 0, 8))
<form method="GET" action="{{ route('browse') }}" class="tm-search" role="search">
    <label class="tm-sr" for="tm-cat-{{ $tmId }}">Kategorie</label>
    <select name="cat" id="tm-cat-{{ $tmId }}">
        <option value="">Alle</option>
        @foreach ($cats as $id => $label)
            <option value="{{ $id }}" @selected(request('cat') == $id)>{{ $label }}</option>
        @endforeach
    </select>
    <label class="tm-sr" for="tm-q-{{ $tmId }}">Suche</label>
    <input id="tm-q-{{ $tmId }}" type="search" name="q" value="{{ request()->routeIs('browse') ? request('q') : '' }}" placeholder="Releases suchen …" autocomplete="off">
    <span class="tm-kbd tm-hide-sm">Strg K</span>
    <button type="submit" aria-label="Suchen">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
    </button>
</form>
