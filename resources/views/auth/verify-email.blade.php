<x-guest-layout>
    <h1 class="tm-h2" style="margin-bottom:10px">E-Mail bestätigen</h1>
    <p class="tm-muted tm-small" style="margin-bottom:18px">Danke für deine Anmeldung. Bitte klicke auf den Link, den wir dir per Mail geschickt haben. Keine Mail bekommen? Wir schicken gern eine neue.</p>
    @if (session('status') == 'verification-link-sent')
        <div class="tm-alert ok" style="margin-bottom:14px">Ein neuer Link ist unterwegs.</div>
    @endif
    <form method="POST" action="{{ route('verification.send') }}">@csrf
        <button class="tm-btn tm-btn-primary" style="width:100%;height:40px">Link erneut schicken</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" style="margin-top:14px;text-align:center">@csrf
        <button class="tm-small tm-muted" style="background:none;border:0;cursor:pointer;text-decoration:underline">Abmelden</button>
    </form>
</x-guest-layout>
