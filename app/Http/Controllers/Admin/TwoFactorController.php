<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Brand;
use App\Support\QrCode;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/** Zwei-Faktor-Anmeldung (TOTP) für Admins */
class TwoFactorController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $pending = $request->session()->get('2fa_pending');
        $qr = $pending ? self::qr($pending, $user->email, Brand::name()) : null;
        return view('admin.two-factor', [
            'enabled' => $user->hasTwoFactor(),
            'pending' => $pending,
            'qr' => $qr,
            'codes' => session('2fa_codes'),
            'remaining' => count((array) $user->two_factor_recovery_codes),
        ]);
    }

    /** QR-Code für die App. Langer Seitenname oder lange E-Mail: kürzere Form, notfalls null (dann Schlüssel von Hand). */
    private static function qr(string $secret, string $email, string $issuer): ?string
    {
        foreach ([Totp::uri($secret, $email, $issuer), Totp::uri($secret, $email, $issuer, false), Totp::uri($secret, $email, mb_substr($issuer, 0, 20), false)] as $uri) {
            try {
                return QrCode::svg($uri, 5);
            } catch (\InvalidArgumentException) {
                // zu lang für den QR-Code, nächste kürzere Form
            }
        }
        return null;
    }

    public function start(Request $request)
    {
        abort_if($request->user()->hasTwoFactor(), 400);
        $request->session()->put('2fa_pending', Totp::secret());
        return redirect()->route('admin.2fa');
    }

    public function confirm(Request $request)
    {
        $user = $request->user();
        $secret = $request->session()->get('2fa_pending');
        $request->validate(['code' => 'required|string|max:10']);
        if (!$secret || ($step = Totp::verify($secret, $request->input('code'))) === null) {
            return back()->withErrors(['code' => 'Der Code stimmt nicht. Uhrzeit am Handy prüfen und den aktuellen Code eingeben.']);
        }
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_last_step' => $step])->save();
        $codes = $user->newRecoveryCodes();
        $request->session()->forget('2fa_pending');
        $request->session()->put('2fa_passed', $user->id);
        Log::notice("Zwei-Faktor eingerichtet für {$user->email}");
        return redirect()->route('admin.2fa')->with('2fa_codes', $codes)->with('status', 'Zwei-Faktor-Anmeldung ist eingeschaltet.');
    }

    public function disable(Request $request)
    {
        $user = $request->user();
        $request->validate(['password' => 'required|string', 'code' => 'required|string|max:20']);
        if (!Hash::check($request->input('password'), $user->password) || !$this->checkCode($user, $request->input('code'))) {
            return back()->withErrors(['disable' => 'Passwort oder Code falsch.']);
        }
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null])->save();
        Log::warning("Zwei-Faktor ausgeschaltet für {$user->email}");
        return redirect()->route('admin.2fa')->with('status', 'Zwei-Faktor-Anmeldung ist ausgeschaltet.');
    }

    public function recovery(Request $request)
    {
        $user = $request->user();
        $request->validate(['code' => 'required|string|max:10']);
        if (!$this->checkCode($user, $request->input('code'), false)) {
            return back()->withErrors(['recovery' => 'Der Code stimmt nicht.']);
        }
        return redirect()->route('admin.2fa')->with('2fa_codes', $user->newRecoveryCodes())->with('status', 'Neue Wiederherstellungscodes erstellt. Die alten gelten nicht mehr.');
    }

    public function challenge(Request $request)
    {
        if (!$request->user()->hasTwoFactor() || $request->session()->get('2fa_passed') === $request->user()->id) {
            return redirect()->route('admin.index');
        }
        return view('admin.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $user = $request->user();
        $request->validate(['code' => 'required|string|max:20']);
        if (!$this->checkCode($user, $request->input('code'))) {
            Log::warning("Falscher Zwei-Faktor-Code für {$user->email} von " . $request->ip());
            return back()->withErrors(['code' => 'Der Code stimmt nicht.']);
        }
        $request->session()->regenerate();
        $request->session()->put('2fa_passed', $user->id);
        return redirect()->intended(route('admin.index'));
    }

    /** 6-stelliger Code oder (wenn erlaubt) Wiederherstellungscode */
    private function checkCode($user, string $code, bool $allowRecovery = true): bool
    {
        $code = trim($code);
        if (preg_match('/^\d{3}\s?\d{3}$/', $code)) {
            $step = Totp::verify((string) $user->two_factor_secret, $code, $user->two_factor_last_step);
            if ($step !== null) {
                $user->forceFill(['two_factor_last_step' => $step])->save();
                return true;
            }
            return false;
        }
        if ($allowRecovery && $user->useRecoveryCode($code)) {
            Log::warning("Wiederherstellungscode benutzt von {$user->email}");
            return true;
        }
        return false;
    }
}
