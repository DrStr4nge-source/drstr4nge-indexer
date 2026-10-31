<x-guest-layout>
    <h1 class="tm-h2" style="margin-bottom:10px">Passwort bestätigen</h1>
    <p class="tm-muted tm-small" style="margin-bottom:18px">Das ist ein geschützter Bereich. Bitte bestätige dein Passwort.</p>
    <form method="POST" action="{{ route('password.confirm') }}" class="tm-stack">@csrf
        <div>
            <label class="tm-label" for="password">Passwort</label>
            <input class="tm-input" id="password" type="password" name="password" required autofocus autocomplete="current-password">
            @error('password')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
        </div>
        <button class="tm-btn tm-btn-primary" style="width:100%;height:40px">Bestätigen</button>
    </form>
</x-guest-layout>
