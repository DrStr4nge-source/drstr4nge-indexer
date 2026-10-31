@if ($paginator->hasPages())
    <nav class="tm-pager" role="navigation" aria-label="Seiten">
        @if ($paginator->onFirstPage())
            <span class="off">&lsaquo;</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Zurück">&lsaquo;</a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="off">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="active" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Weiter">&rsaquo;</a>
        @else
            <span class="off">&rsaquo;</span>
        @endif

        <span class="info">Seite {{ $paginator->currentPage() }} von {{ $paginator->lastPage() }} · {{ number_format($paginator->total(), 0, ',', '.') }} Einträge</span>
    </nav>
@endif
