<x-app-layout>
    @unless ($user->isVip())
        <div class="tm-alert info">
            <span>Heute {{ $user->grabsToday() }} von {{ config('indexer.free_grabs_per_day') }} kostenlosen Downloads genutzt. Mit VIP gibt es kein Limit.</span>
            <a href="{{ route('vip') }}" class="tm-btn tm-btn-sm">VIP ansehen</a>
        </div>
    @endunless

    @if ($hasMedia)
        <x-poster-row eyebrow="Neu eingetroffen" title="Die neuesten Filme im Index." :link="route('movies')" link-label="Alle Filme" :items="$movies" />
        <x-poster-row eyebrow="Neu eingetroffen" title="Frische Folgen und Staffeln." :link="route('shows')" link-label="Alle Serien" :items="$shows" />
    @elseif ($user->is_admin)
        <div class="tm-alert warn">
            <span>Noch keine Cover und Filmdaten. Trage <code>TMDB_API_KEY</code> in die <code>.env</code> ein, dann holt der Cronjob sie automatisch (oder von Hand: <code>php artisan indexer:metadata</code>).</span>
        </div>
    @endif

    <section class="tm-panel">
        <div class="tm-panel-head">
            <div>
                <div class="tm-eyebrow">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    Gerade reingekommen
                </div>
                <h2 class="tm-h2">Neueste Releases.</h2>
            </div>
            <a href="{{ route('browse') }}" class="tm-btn">Alle Releases</a>
        </div>
        <x-release-table :releases="$releases" />
    </section>
</x-app-layout>
