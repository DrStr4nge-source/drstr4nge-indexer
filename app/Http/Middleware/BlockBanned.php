<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Gesperrte Accounts werden abgemeldet (gilt für die ganze Seite, nicht nur für Downloads). */
class BlockBanned
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->is_banned) {
            $guard = Auth::guard('web');
            if ($guard instanceof \Illuminate\Contracts\Auth\StatefulGuard) {
                $guard->logout();
            }
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
            return redirect()->route('login')->withErrors(['email' => 'Dieser Account ist gesperrt.']);
        }
        return $next($request);
    }
}
