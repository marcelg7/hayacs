<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\TwoFactorRememberedDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();

        // Check if user has 2FA enabled
        if ($user->hasTwoFactorEnabled()) {
            // Check for remembered device cookie - skip 2FA challenge if valid
            $rememberToken = $request->cookie('2fa_remember');
            if ($rememberToken) {
                $rememberedDevice = TwoFactorRememberedDevice::findValidByToken($rememberToken);

                if ($rememberedDevice && $rememberedDevice->user_id === $user->id) {
                    // Device is remembered - mark session as verified and skip challenge
                    session(['two_factor_verified' => true]);
                    $rememberedDevice->touchLastUsed();
                    $user->update(['last_login_at' => now()]);

                    return redirect()->intended(route('dashboard', absolute: false));
                }
            }

            // No valid remembered device - redirect to 2FA challenge
            return redirect()->route('two-factor.challenge');
        }

        // Record last login time for users without 2FA
        $user->update(['last_login_at' => now()]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
