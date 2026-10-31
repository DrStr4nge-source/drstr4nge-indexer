@php
    $tmUser = auth()->user();
    $tmCats = collect(config('indexer.categories'))->filter(fn ($c) => $c[1] === null)->map(fn ($c) => $c[0]);
    $tmLinks = [
        ['Start', route('dashboard'), request()->routeIs('dashboard')],
        ['Releases', route('browse'), request()->routeIs('browse') && !request('cat')],
        ['Filme', route('movies'), request()->routeIs('movies')],
        ['Serien', route('shows'), request()->routeIs('shows')],
        ['Merkliste', route('watchlist'), request()->routeIs('watchlist')],
        ['VIP', route('vip'), request()->routeIs('vip')],
    ];
    if ($tmUser?->is_admin) {
        $tmLinks[] = ['Admin', route('admin.index'), request()->routeIs('admin.*')];
    }
@endphp
<nav class="tm-nav">
    <div class="tm-container">
        <div class="tm-nav-row">
            <details class="tm-menu tm-burger">
                <summary aria-label="Menü">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </summary>
                <div class="tm-dropdown">
                    @foreach ($tmLinks as [$label, $url, $active])
                        <a href="{{ $url }}">{{ $label }}</a>
                    @endforeach
                </div>
            </details>

            @include('layouts.logo')

            <div class="tm-navlinks">
                @foreach ($tmLinks as [$label, $url, $active])
                    <a href="{{ $url }}" @class(['is-active' => $active])>{{ $label }}</a>
                @endforeach
            </div>

            @include('layouts.search', ['cats' => $tmCats])

            <div class="tm-nav-right">
                @if ($tmUser)
                    @if ($tmUser->isVip())
                        <a href="{{ route('vip') }}" class="tm-pill is-vip tm-hide-sm" title="VIP bis {{ \App\Support\Format::dt($tmUser->vip_until, 'd.m.Y') }}">VIP</a>
                    @else
                        <a href="{{ route('vip') }}" class="tm-pill tm-hide-sm">VIP holen</a>
                    @endif
                    <details class="tm-menu">
                        <summary><span class="tm-avatar">{{ mb_substr($tmUser->name, 0, 2) }}</span></summary>
                        <div class="tm-dropdown">
                            <div class="tm-dd-head">
                                <div style="font-weight:600">{{ $tmUser->name }}</div>
                                <div class="tm-muted tm-small">{{ $tmUser->email }}</div>
                            </div>
                            <a href="{{ route('account') }}">Mein Konto &amp; API-Key</a>
                            <a href="{{ route('profile.edit') }}">Profil &amp; Passwort</a>
                            <a href="{{ route('vip') }}">VIP</a>
                            @if ($tmUser->is_admin)
                                <a href="{{ route('admin.settings') }}">Einstellungen</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">@csrf
                                <button type="submit">Abmelden</button>
                            </form>
                        </div>
                    </details>
                @endif
            </div>
        </div>
        <div class="tm-search-mobile">@include('layouts.search', ['cats' => $tmCats])</div>
    </div>
</nav>
