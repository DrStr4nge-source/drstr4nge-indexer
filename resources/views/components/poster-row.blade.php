@props(['eyebrow', 'title', 'link' => null, 'linkLabel' => 'Alle anzeigen', 'items'])
<section class="tm-panel">
    <div class="tm-panel-head">
        <div>
            <div class="tm-eyebrow">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 17 6-6 4 4 8-8"/><path d="M14 7h7v7"/></svg>
                {{ $eyebrow }}
            </div>
            <h2 class="tm-h2">{{ $title }}</h2>
        </div>
        @if ($link)
            <a href="{{ $link }}" class="tm-btn">{{ $linkLabel }}</a>
        @endif
    </div>
    @if ($items->isEmpty())
        <div class="tm-empty">{{ $empty ?? 'Noch nichts da.' }}</div>
    @else
        <div class="tm-row">
            <button type="button" class="tm-arrow left" data-scroll="left" aria-label="Zurück">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg>
            </button>
            <div class="tm-scroller">
                @foreach ($items as $m)
                    <x-poster-card :media="$m" :date="$m->last_posted" />
                @endforeach
            </div>
            <button type="button" class="tm-arrow right" data-scroll="right" aria-label="Weiter">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg>
            </button>
        </div>
    @endif
</section>
