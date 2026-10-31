<?php
namespace App\Http\Controllers;

use App\Models\Invite;
use App\Models\User;
use App\Support\Registration;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

/** Registrierung mit Modus (offen, Einladung, geschlossen) und Bot-Falle. Ersetzt die Breeze-Route /register. */
class RegisterController extends Controller
{
    public function create(Request $request)
    {
        return view('auth.register', [
            'mode' => Registration::mode(),
            'code' => Invite::normalize($request->query('code')),
        ]);
    }

    public function store(Request $request)
    {
        $mode = Registration::mode();
        abort_if($mode === 'closed', 403, 'Die Registrierung ist geschlossen.');

        // Bot-Falle: dieses Feld ist für Menschen unsichtbar und muss leer bleiben
        if (filled($request->input('website'))) {
            return redirect()->route('register');
        }

        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'invite' => [$mode === 'invite' ? 'required' : 'nullable', 'string', 'max:40'],
        ], [
            'invite.required' => 'Bitte einen Einladungscode eingeben.',
            'email.unique' => 'Diese E-Mail-Adresse ist schon registriert.',
        ]);

        $user = DB::transaction(function () use ($data, $mode) {
            $invite = null;
            if ($mode === 'invite') {
                $invite = Invite::where('code', Invite::normalize($data['invite']))->whereNull('used_by')->lockForUpdate()->first();
                if (!$invite) {
                    throw ValidationException::withMessages(['invite' => 'Dieser Einladungscode ist ungültig oder schon benutzt.']);
                }
            }
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $invite?->update(['used_by' => $user->id, 'used_at' => now()]);
            return $user;
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('status', 'Willkommen! Dein Konto ist eingerichtet.');
    }
}
