<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user?->is_admin, 403);

        // Zwei-Faktor: nach dem Login einmal pro Sitzung den Code abfragen
        if ($user->hasTwoFactor() && $request->session()->get('2fa_passed') !== $user->id && !$request->routeIs('admin.2fa.challenge*')) {
            return redirect()->guest(route('admin.2fa.challenge'));
        }
        // Optional Pflicht für alle Admins (Admin > Einstellungen)
        if (!$user->hasTwoFactor() && \App\Models\Setting::get('admin_require_2fa') === '1' && !$request->routeIs('admin.2fa*')) {
            return redirect()->route('admin.2fa')->with('status', 'Bitte zuerst die Zwei-Faktor-Anmeldung einrichten.');
        }
        return $next($request);
    }
}
