<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordSetupController extends Controller
{
    /**
     * Show the password setup form.
     */
    public function show(Request $request, User $user)
    {
        // Verify the signed URL
        if (!$request->hasValidSignature()) {
            abort(403, 'This password setup link has expired or is invalid.');
        }

        // Check if user already has a password set (not the placeholder)
        if ($user->password && !$user->must_change_password) {
            return redirect()->route('login')
                ->with('status', 'Your password has already been set. Please log in.');
        }

        return view('auth.setup-password', ['user' => $user]);
    }

    /**
     * Handle the password setup.
     */
    public function store(Request $request, User $user)
    {
        // Verify the signed URL
        if (!$request->hasValidSignature()) {
            abort(403, 'This password setup link has expired or is invalid.');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        return redirect()->route('login')
            ->with('status', 'Your password has been set successfully. You can now log in.');
    }
}
