<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\TwoFactorRememberedDevice;
use App\Services\TrustedDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    protected TrustedDeviceService $trustedDeviceService;

    public function __construct(TrustedDeviceService $trustedDeviceService)
    {
        $this->trustedDeviceService = $trustedDeviceService;
    }

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
            // First, check for trusted device token (90-day trust + IP bypass)
            $trustedDevice = $this->trustedDeviceService->getValidTrustedDevice($request);
            if ($trustedDevice && $trustedDevice->user_id === $user->id) {
                // Trusted device - skip 2FA challenge
                session(['two_factor_verified' => true]);
                $fingerprintMatched = $this->trustedDeviceService->verifyFingerprint($request, $trustedDevice);
                $trustedDevice->recordUse('login_2fa_skip', $fingerprintMatched);
                $user->update(['last_login_at' => now()]);

                \Log::info('2FA skipped via trusted device during login', [
                    'user_id' => $user->id,
                    'trusted_device_id' => $trustedDevice->id,
                    'fingerprint_matched' => $fingerprintMatched,
                ]);

                return redirect()->intended(route('dashboard', absolute: false));
            }

            // Check for remembered device cookie (48-day 2FA remember)
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

            // No valid trusted/remembered device - redirect to 2FA challenge
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
