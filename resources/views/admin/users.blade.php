<x-app-layout>
    <x-slot name="title">Admin: User</x-slot>
    <x-slot name="header"><h1 class="tm-h1">User</h1></x-slot>
    <div>
        @include('admin._nav')
        <section class="tm-panel">
            <form method="GET" class="tm-form-row" style="margin-bottom:16px">
                <input class="tm-input grow" name="q" value="{{ request('q') }}" placeholder="Name oder E-Mail suchen">
                <button class="tm-btn">Suchen</button>
            </form>
            <div class="tm-table-wrap">
                <table class="tm-table">
                    <thead><tr><th>E-Mail</th><th>VIP bis</th><th>Rolle</th><th>Aktion</th></tr></thead>
                    <tbody>
                    @foreach ($users as $u)
                        <tr>
                            <td>{{ $u->email }} @if ($u->is_banned)<span class="tm-chip" style="color:var(--tm-bad)">gesperrt</span>@endif</td>
                            <td class="nowrap">@if ($u->isVip())<span class="tm-chip accent">{{ \App\Support\Format::dt($u->vip_until, 'd.m.Y') }}</span>@else <span class="tm-muted">–</span> @endif</td>
                            <td>{{ $u->is_admin ? 'Admin' : 'User' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.users.update', $u) }}" class="tm-form-row" style="flex-wrap:nowrap">@csrf
                                    <input type="number" name="days" value="30" min="1" class="tm-input tm-input-sm" style="width:70px" title="Tage">
                                    <select name="action" class="tm-select tm-input-sm" style="width:auto">
                                        <option value="add_vip">VIP + Tage</option>
                                        <option value="remove_vip">VIP entfernen</option>
                                        <option value="ban">Sperren</option>
                                        <option value="unban">Entsperren</option>
                                        <option value="make_admin">Zum Admin</option>
                                        <option value="remove_admin">Admin entfernen</option>
                                    </select>
                                    <button class="tm-btn tm-btn-sm">OK</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $users->links() }}
        </section>
    </div>
</x-app-layout>
