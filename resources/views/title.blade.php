<x-app-layout>
    <x-slot name="title">{{ $media->title }}</x-slot>

    <section class="tm-hero">
        @if ($media->coverUrl())
            <div class="tm-hero-bg" style="background-image:url('{{ $media->coverUrl() }}')"></div>
        @endif
        <div class="tm-hero-inner">
            <div class="tm-hero-poster">
                <div class="tm-poster">
                    @if ($media->coverUrl())
                        <img src="{{ $media->coverUrl() }}" alt="{{ $media->title }}">
                    @else
                        <div class="tm-poster-fallback">{{ $media->title }}</div>
                    @endif
                </div>
            </div>
            <div class="tm-hero-text">
                <div class="tm-eyebrow">{{ $media->type === 'tv' ? 'Serie' : 'Film' }}</div>
                <h1 class="tm-h1">{{ $media->title }} @if ($media->year)<span>({{ $media->year }})</span>@endif</h1>
                <div class="tm-meta">
                    @if ($media->rating)<span class="tm-chip accent">&#9733; {{ $media->rating }}/10</span>@endif
                    <span class="tm-chip">{{ $releases->total() }} {{ $releases->total() === 1 ? 'Release' : 'Releases' }}</span>
                </div>
                @if ($media->overview)
                    <p class="tm-muted" style="max-width:70ch">{{ $media->overview }}</p>
                @endif
                <div class="tm-hero-actions">
                    @php($tmWatched = \App\Models\Watch::where('user_id', auth()->id())->where('media_id', $media->id)->exists())
                    <form method="POST" action="{{ route('watch.toggle', $media) }}">@csrf
                        <button class="tm-btn {{ $tmWatched ? '' : 'tm-btn-primary' }}">{{ $tmWatched ? '✓ Gemerkt' : '+ Merken' }}</button>
                    </form>
                    @if ($media->imdbUrl())<a class="tm-btn" href="{{ $media->imdbUrl() }}" target="_blank" rel="noopener">IMDb</a>@endif
                    @if ($media->tmdbUrl())<a class="tm-btn" href="{{ $media->tmdbUrl() }}" target="_blank" rel="noopener">TMDB</a>@endif
                </div>
            </div>
        </div>
    </section>

    <section class="tm-panel">
        <div class="tm-panel-head">
            <div>
                <div class="tm-eyebrow">Downloads</div>
                <h2 class="tm-h2">Alle Releases zu diesem Titel.</h2>
            </div>
        </div>
        <x-release-table :releases="$releases" :show-cover="false" :show-title="false" />
        {{ $releases->links() }}
    </section>
</x-app-layout>
