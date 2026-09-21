<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public const DEACTIVATED_MESSAGE = 'Your account has been deactivated. Contact CLSD Legal if you believe this is a mistake.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // A purged session or revoked remember token arrives as a guest. Let
        // the existing auth middleware send that request to the login page.
        if ($user === null || $user->is_active) {
            return $next($request);
        }

        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Livewire follows this redirect inside fetch before navigating the
        // browser. A flash would be consumed by that hidden GET /login.
        if ($request->hasHeader('X-Livewire')) {
            return redirect()->route('login', ['deactivated' => 1]);
        }

        // Flash after invalidation so the message survives the new session.
        return redirect()->route('login')->with('status', self::DEACTIVATED_MESSAGE);
    }
}
