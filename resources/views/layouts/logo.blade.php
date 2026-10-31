@php([$tmFirst, $tmLast] = \App\Support\Brand::nameParts())
<a href="{{ route('dashboard') }}" class="tm-logo" aria-label="{{ \App\Support\Brand::name() }}">
    @if ($tmLogo = \App\Support\Brand::logoUrl())
        <img src="{{ $tmLogo }}" alt="{{ \App\Support\Brand::name() }}" style="height:32px;width:auto;max-width:180px;object-fit:contain">
    @else
        <span class="tm-logo-mark">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5"/><path d="m2 12 10 5 10-5"/></svg>
        </span>
        <span class="tm-logo-text">{{ $tmFirst }}<b>{{ $tmLast }}</b></span>
    @endif
</a>
