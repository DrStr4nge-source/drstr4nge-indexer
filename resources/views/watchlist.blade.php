<x-app-layout>
    <x-slot name="title">Merkliste</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Merkliste</h1></x-slot>

    <section class="tm-panel">
        <div class="tm-panel-head">
            <div>
                <div class="tm-eyebrow">Gemerkt</div>
                <h2 class="tm-h2">{{ $items->count() }} Titel</h2>
            </div>
            <div class="tm-form-row" style="gap:8px">
                <input class="tm-input" value="{{ route('rss', ['apikey' => $user->api_key, 't' => 'merkliste']) }}" readonly onfocus="this.select()" style="width:320px;height:32px;font-size:12.5px">
                <button type="button" class="tm-btn tm-btn-sm" data-copy="{{ route('rss', ['apikey' => $user->api_key, 't' => 'merkliste']) }}">RSS kopieren</button>
            </div>
        </div>
        @if ($items->isEmpty())
            <div class="tm-empty">Noch nichts gemerkt. Auf der Seite eines Films oder einer Serie auf „Merken“ klicken.</div>
        @else
            <div style="display:flex;flex-wrap:wrap;gap:14px">
                @foreach ($items as $w)
                    <div class="tm-card" style="position:relative">
                        <a href="{{ route('title', $w->media) }}">
                            <div class="tm-poster">
                                @if ($w->media->coverUrl())<img src="{{ $w->media->coverUrl() }}" alt="{{ $w->media->title }}" loading="lazy">@else<div class="tm-poster-fallback">{{ $w->media->title }}</div>@endif
                            </div>
                        </a>
                        <div class="tm-card-body">
                            <div class="tm-card-title">{{ $w->media->title }}</div>
                            <form method="POST" action="{{ route('watch.toggle', $w->media) }}" style="margin-top:6px">@csrf
                                <button class="tm-btn tm-btn-sm" style="width:100%">Entfernen</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    @if ($items->isNotEmpty())
        <section class="tm-panel">
            <div class="tm-panel-head"><div><div class="tm-eyebrow">Downloads</div><h2 class="tm-h2">Neue Releases zu deinen Titeln</h2></div></div>
            <x-release-table :releases="$releases" />
            {{ $releases->links() }}
        </section>
    @endif
</x-app-layout>
