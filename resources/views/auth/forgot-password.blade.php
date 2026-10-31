<x-guest-layout>
    <h1 class="tm-h2" style="margin-bottom:10px">Passwort vergessen</h1>
    <p class="tm-muted tm-small" style="margin-bottom:18px">Gib deine E-Mail-Adresse ein. Du bekommst einen Link, mit dem du ein neues Passwort festlegen kannst.</p>
    @if (session('status'))
        <div class="tm-alert ok" style="margin-bottom:14px">Wenn die Adresse bei uns registriert ist, ist der Link unterwegs. Schau auch im Spam-Ordner nach.</div>
    @endif
    <form method="POST" action="{{ route('password.email') }}" class="tm-stack">@csrf
        <div>
            <label class="tm-label" for="email">E-Mail</label>
            <input class="tm-input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email')<ul class="tm-error"><li>{{ $message }}</li></ul>@enderror
        </div>
        <button class="tm-btn tm-btn-primary" style="width:100%;height:40px">Link schicken</button>
    </form>
    <p class="tm-muted tm-small" style="margin-top:16px;text-align:center"><a class="tm-link" href="{{ route('login') }}">Zurück zur Anmeldung</a></p>
</x-guest-layout>
