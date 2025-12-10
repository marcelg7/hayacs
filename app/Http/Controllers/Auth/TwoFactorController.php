<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorRememberedDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    protected Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Show the 2FA challenge form (after login)
     */
    public function showChallenge(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard');
        }

        if (session('two_factor_verified')) {
            return redirect()->route('dashboard');
        }

        return view('auth.two-factor-challenge');
    }

    /**
     * Verify the 2FA code
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'digits:6'],
            'remember_device' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return redirect()->route('dashboard');
        }

        // Verify TOTP code
        $valid = $this->google2fa->verifyKey(
            $user->two_factor_secret,
            $request->code,
            2 // Allow 2 windows of drift (60 seconds tolerance)
        );

        if (!$valid) {
            return back()->withErrors([
                'code' => 'The provided code is invalid. Please try again.',
            ]);
        }

        // Mark session as 2FA verified
        session(['two_factor_verified' => true]);

        // Record last login time after successful 2FA verification
        $user->update(['last_login_at' => now()]);

        // Clear any potentially problematic intended URLs and redirect to dashboard
        session()->forget('url.intended');
        $response = redirect()->route('dashboard')
            ->with('success', 'Two-factor authentication verified.');

        // If user requested to remember this device, create a remembered device token
        if ($request->boolean('remember_device')) {
            $deviceName = $this->getDeviceName($request);
            $rememberedDevice = TwoFactorRememberedDevice::createForUser(
                $user,
                $deviceName,
                $request->ip()
            );

            // Set a secure cookie that expires in 48 days
            $response->withCookie(Cookie::make(
                '2fa_remember',
                $rememberedDevice->token,
                60 * 24 * 48, // 48 days in minutes
                '/',
                null,
                true, // secure (HTTPS only)
                true  // httpOnly
            ));
        }

        return $response;
    }

    /**
     * Get a friendly device name from the request
     */
    protected function getDeviceName(Request $request): string
    {
        $userAgent = $request->userAgent() ?? 'Unknown';

        // Try to extract browser and OS info
        $browser = 'Unknown Browser';
        $os = 'Unknown OS';

        // Detect browser
        if (str_contains($userAgent, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($userAgent, 'Edg')) {
            $browser = 'Edge';
        } elseif (str_contains($userAgent, 'Chrome')) {
            $browser = 'Chrome';
        } elseif (str_contains($userAgent, 'Safari')) {
            $browser = 'Safari';
        }

        // Detect OS
        if (str_contains($userAgent, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($userAgent, 'Mac')) {
            $os = 'macOS';
        } elseif (str_contains($userAgent, 'Linux')) {
            $os = 'Linux';
        } elseif (str_contains($userAgent, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            $os = 'iOS';
        }

        return "{$browser} on {$os}";
    }

    /**
     * Show the 2FA setup page
     */
    public function showSetup(Request $request): View
    {
        $user = $request->user();

        // Generate a new secret if not in setup flow
        $secret = session('2fa_setup_secret');
        if (!$secret) {
            $secret = $this->google2fa->generateSecretKey();
            session(['2fa_setup_secret' => $secret]);
        }

        // Generate QR code
        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $qrCodeSvg = $this->generateQrCodeSvg($qrCodeUrl);

        // Calculate grace period info
        $graceDaysRemaining = $user->getTwoFactorGraceDaysRemaining();
        $graceExpired = $user->requiresTwoFactorSetup();

        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'qrCodeSvg' => $qrCodeSvg,
            'graceDaysRemaining' => $graceDaysRemaining,
            'graceExpired' => $graceExpired,
            'user' => $user,
        ]);
    }

    /**
     * Enable 2FA (verify and save)
     */
    public function enable(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $user = $request->user();
        $secret = session('2fa_setup_secret');

        if (!$secret) {
            return redirect()->route('two-factor.setup')
                ->withErrors(['code' => 'Session expired. Please try again.']);
        }

        // Verify the code before enabling
        $valid = $this->google2fa->verifyKey($secret, $request->code, 2);

        if (!$valid) {
            return back()->withErrors([
                'code' => 'The code is invalid. Please scan the QR code and try again.',
            ]);
        }

        // Enable 2FA
        $user->enableTwoFactor($secret);

        // Clear setup session and mark as verified
        session()->forget('2fa_setup_secret');
        session(['two_factor_verified' => true]);

        return redirect()->route('dashboard')
            ->with('success', 'Two-factor authentication has been enabled successfully.');
    }

    /**
     * Generate QR code as SVG
     */
    protected function generateQrCodeSvg(string $url): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }
}
