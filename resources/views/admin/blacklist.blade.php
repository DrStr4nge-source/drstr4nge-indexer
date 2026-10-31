<x-app-layout>
    <x-slot name="title">Admin: Blacklist</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Blacklist</h1></x-slot>
    <div>
        @include('admin._nav')
        <section class="tm-panel">
            <p class="tm-muted tm-small" style="margin-bottom:12px">Treffer werden beim Crawlen als Spam markiert. Poster: Platzhalter * erlaubt (z.B. <code>spammer*@*</code>). Name: regulärer Ausdruck.</p>
            <form method="POST" action="{{ route('admin.blacklist.store') }}" class="tm-form-row">@csrf
                <select name="type" class="tm-select" style="width:auto">
                    <option value="poster">Poster</option>
                    <option value="name_regex">Name (Regex)</option>
                </select>
                <input class="tm-input grow" name="pattern" required>
                <button class="tm-btn tm-btn-primary">Hinzufügen</button>
            </form>
            @error('pattern')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
            <div class="tm-list" style="margin-top:14px">
                @forelse ($rules as $r)
                    <div>
                        <span><span class="tm-chip">{{ $r->type === 'poster' ? 'Poster' : 'Regex' }}</span> <code>{{ $r->pattern }}</code></span>
                        <form method="POST" action="{{ route('admin.blacklist.destroy', $r) }}">@csrf @method('DELETE')<button class="tm-btn tm-btn-sm tm-btn-danger">Löschen</button></form>
                    </div>
                @empty
                    <div class="tm-muted">Keine Regeln.</div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
