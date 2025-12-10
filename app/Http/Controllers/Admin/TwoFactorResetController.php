<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TwoFactorRememberedDevice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TwoFactorResetController extends Controller
{
    /**
     * Reset 2FA for a user (admin only)
     */
    public function reset(Request $request, User $user): RedirectResponse
    {
        // Prevent self-reset
        if ($user->id === $request->user()->id) {
            return redirect()->route('users.edit', $user)
                ->with('error', 'You cannot reset your own two-factor authentication.');
        }

        // Log the action
        Log::info('2FA reset by admin', [
            'admin_id' => $request->user()->id,
            'admin_email' => $request->user()->email,
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);

        // Disable 2FA (this also resets grace period)
        $user->disableTwoFactor();

        // Revoke all remembered devices for the user
        TwoFactorRememberedDevice::revokeAllForUser($user);

        return redirect()->route('users.edit', $user)
            ->with('success', "Two-factor authentication has been reset for {$user->name}. They will have 14 days to set it up again.");
    }
}
