<x-app-layout>
    <x-slot name="title">Admin: Zahlungen</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Zahlungen</h1></x-slot>
    <div>
        @include('admin._nav')
        <section class="tm-panel">
            <div class="tm-table-wrap">
                <table class="tm-table">
                    <thead><tr><th>Datum</th><th>User</th><th>Plan</th><th class="num">Betrag</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($payments as $p)
                        <tr>
                            <td class="nowrap tm-muted">{{ \App\Support\Format::dt($p->created_at, 'd.m.Y H:i') }}</td>
                            <td>{{ $p->user?->email }}</td>
                            <td>{{ config("indexer.plans.{$p->plan}.label", $p->plan) }}</td>
                            <td class="num">{{ number_format((float) $p->amount, 2, ',', '.') }} {{ $p->currency }}</td>
                            <td>@if ($p->status === 'paid')<span class="tm-chip accent">bezahlt</span>@else<span class="tm-chip">offen</span>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="tm-empty">Noch keine Zahlungen.</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $payments->links() }}
        </section>
    </div>
</x-app-layout>
