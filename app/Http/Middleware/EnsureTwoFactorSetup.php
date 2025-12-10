<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorSetup
{
    /**
     * Routes that should be accessible when 2FA setup is required
     */
    protected array $allowedRoutes = [
        'two-factor.setup',
        'two-factor.enable',
        'two-factor.challenge',
        'two-factor.verify',
        'logout',
        'password.change',
        'password.change.update',
    ];

    /**
     * Handle an incoming request.
     *
     * Redirect users whose grace period has expired to the 2FA setup page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Skip if route is allowed
        if ($request->routeIs(...$this->allowedRoutes)) {
            return $next($request);
        }

        // Check if 2FA setup is required (grace period expired)
        if ($user->requiresTwoFactorSetup()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'error' => 'Two-factor authentication setup required',
                    'message' => 'Your grace period has expired. Please set up two-factor authentication.',
                ], 403);
            }

            return redirect()->route('two-factor.setup')
                ->with('warning', 'Your 14-day grace period has expired. Please set up two-factor authentication to continue.');
        }

        return $next($request);
    }
}
