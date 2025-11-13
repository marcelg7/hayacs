<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip if user is not authenticated or already on password change page
        if (!$user || $request->routeIs('password.*') || $request->routeIs('logout')) {
            return $next($request);
        }

        // Redirect to password change if required
        if ($user->must_change_password) {
            return redirect()->route('password.change')
                ->with('warning', 'You must change your temporary password before continuing.');
        }

        return $next($request);
    }
}
