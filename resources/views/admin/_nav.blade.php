<nav class="tm-tabs">
    @foreach ([
        ['admin.index', 'Übersicht'], ['admin.users', 'User'], ['admin.groups', 'Newsgroups'],
        ['admin.releases', 'Releases'], ['admin.predb', 'PreDB'], ['admin.reports', 'Meldungen'], ['admin.blacklist', 'Blacklist'], ['admin.payments', 'Zahlungen'],
        ['admin.invites', 'Einladungen'], ['admin.credentials', 'Zugangsdaten'], ['admin.settings', 'Einstellungen'], ['admin.2fa', 'Zwei-Faktor'], ['admin.log', 'Log'],
    ] as [$tmRoute, $tmLabel])
        @php
            $tmBadge = match ($tmRoute) {
                'admin.reports' => \App\Models\ReleaseReport::where('status', 'open')->count(),
                'admin.log' => count(\Illuminate\Support\Facades\Cache::get('health.problems', [])),
                default => 0,
            };
        @endphp
        <a href="{{ route($tmRoute) }}" @class(['is-active' => request()->routeIs($tmRoute)])>{{ $tmLabel }}
            @if ($tmBadge && !request()->routeIs($tmRoute))
                <span class="tm-chip bad" style="margin-left:4px">{{ $tmBadge }}</span>
            @endif
        </a>
    @endforeach
</nav>
