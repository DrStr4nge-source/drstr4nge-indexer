<nav class="tm-tabs">
    @foreach ([
        ['admin.index', 'Übersicht'], ['admin.users', 'User'], ['admin.groups', 'Newsgroups'],
        ['admin.releases', 'Releases'], ['admin.blacklist', 'Blacklist'], ['admin.payments', 'Zahlungen'],
        ['admin.invites', 'Einladungen'], ['admin.settings', 'Einstellungen'],
    ] as [$tmRoute, $tmLabel])
        <a href="{{ route($tmRoute) }}" @class(['is-active' => request()->routeIs($tmRoute)])>{{ $tmLabel }}</a>
    @endforeach
</nav>
