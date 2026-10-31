<x-app-layout>
    <x-slot name="title">Profil</x-slot>
    <x-slot name="header"><h1 class="tm-h1">Profil &amp; Passwort</h1></x-slot>

    <div class="tm-stack" style="max-width:720px">
        <section class="tm-panel">
            <h2 class="tm-h2" style="margin-bottom:14px">Name und E-Mail</h2>
            @if (session('status') === 'profile-updated')<div class="tm-alert ok" style="margin-bottom:14px">Gespeichert.</div>@endif
            <form method="POST" action="{{ route('profile.update') }}" class="tm-stack">@csrf @method('patch')
                <div>
                    <label class="tm-label" for="name">Name</label>
                    <input class="tm-input" id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name">
                    @error('name')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                </div>
                <div>
                    <label class="tm-label" for="email">E-Mail</label>
                    <input class="tm-input" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="username">
                    @error('email')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                </div>
                <div><button class="tm-btn tm-btn-primary">Speichern</button></div>
            </form>
        </section>

        <section class="tm-panel">
            <h2 class="tm-h2" style="margin-bottom:14px">Passwort ändern</h2>
            @if (session('status') === 'password-updated')<div class="tm-alert ok" style="margin-bottom:14px">Passwort geändert.</div>@endif
            <form method="POST" action="{{ route('password.update') }}" class="tm-stack">@csrf @method('put')
                <div>
                    <label class="tm-label" for="current_password">Aktuelles Passwort</label>
                    <input class="tm-input" id="current_password" type="password" name="current_password" autocomplete="current-password">
                    @error('current_password', 'updatePassword')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                </div>
                <div>
                    <label class="tm-label" for="password">Neues Passwort</label>
                    <input class="tm-input" id="password" type="password" name="password" autocomplete="new-password">
                    @error('password', 'updatePassword')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                </div>
                <div>
                    <label class="tm-label" for="password_confirmation">Neues Passwort wiederholen</label>
                    <input class="tm-input" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password">
                    @error('password_confirmation', 'updatePassword')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
                </div>
                <div><button class="tm-btn tm-btn-primary">Passwort ändern</button></div>
            </form>
        </section>

        <section class="tm-panel">
            <h2 class="tm-h2" style="margin-bottom:8px">Konto löschen</h2>
            <p class="tm-muted tm-small" style="margin-bottom:14px">Löscht dein Konto mit Merkliste und Verlauf. Das lässt sich nicht rückgängig machen.</p>
            <details @if ($errors->userDeletion->isNotEmpty()) open @endif>
                <summary class="tm-btn tm-btn-sm" style="list-style:none;display:inline-flex">Konto löschen …</summary>
                <form method="POST" action="{{ route('profile.destroy') }}" class="tm-form-row" style="gap:8px;margin-top:12px;flex-wrap:wrap">@csrf @method('delete')
                    <input class="tm-input" type="password" name="password" placeholder="Passwort zur Bestätigung" style="max-width:260px" autocomplete="current-password">
                    <button class="tm-btn" style="border-color:var(--tm-bad);color:var(--tm-bad) !important">Endgültig löschen</button>
                </form>
                @error('password', 'userDeletion')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
            </details>
        </section>
    </div>
</x-app-layout>
