<?php

namespace App\Http\Middleware;

use App\Models\TwoFactorRememberedDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorChallenge
{
    /**
     * Routes that should be accessible during 2FA challenge
     */
    protected array $allowedRoutes = [
        'two-factor.challenge',
        'two-factor.verify',
        'logout',
    ];

    /**
     * Handle an incoming request.
     *
     * Redirect users with 2FA enabled to the challenge page if their
     * session hasn't been verified yet.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Skip if route is allowed during challenge
        if ($request->routeIs(...$this->allowedRoutes)) {
            return $next($request);
        }

        // Check if user has 2FA enabled and session is not verified
        if ($user->hasTwoFactorEnabled() && !session('two_factor_verified')) {
            // Check for remembered device cookie
            $rememberToken = $request->cookie('2fa_remember');
            if ($rememberToken) {
                $rememberedDevice = TwoFactorRememberedDevice::findValidByToken($rememberToken);

                // Verify the device belongs to this user
                if ($rememberedDevice && $rememberedDevice->user_id === $user->id) {
                    // Mark session as verified and update last used
                    session(['two_factor_verified' => true]);
                    $rememberedDevice->touchLastUsed();

                    // Update last login time
                    $user->update(['last_login_at' => now()]);

                    return $next($request);
                }
            }

            // Store intended URL for redirect after verification
            if (!$request->ajax() && !$request->wantsJson()) {
                session(['url.intended' => $request->fullUrl()]);
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Two-factor authentication required'], 403);
            }

            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
