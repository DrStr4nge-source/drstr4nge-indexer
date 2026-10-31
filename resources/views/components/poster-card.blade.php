@props(['media', 'date' => null])
<a href="{{ route('title', $media) }}" class="tm-card" title="{{ $media->title }}">
    <div class="tm-poster">
        @if ($media->coverUrl())
            <img src="{{ $media->coverUrl() }}" alt="" loading="lazy">
        @else
            <div class="tm-poster-fallback">{{ $media->title }}</div>
        @endif
        @if ($media->rating)
            <span class="tm-rating">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg>
                {{ rtrim(rtrim(number_format((float) $media->rating, 1, '.', ''), '0'), '.') }}/10
            </span>
        @endif
    </div>
    <div class="tm-card-body">
        <div class="tm-card-title">{{ $media->title }}</div>
        <div class="tm-meta">
            @if ($media->year)<span class="tm-chip">{{ $media->year }}</span>@endif
            @if ($date)<span>{{ \App\Support\Format::dt($date, 'd.m.Y') }}</span>@endif
        </div>
    </div>
</a>
