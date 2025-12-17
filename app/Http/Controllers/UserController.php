<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\WelcomeNewUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        // Validate sort field to prevent SQL injection
        $allowedSortFields = ['name', 'email', 'role', 'must_change_password', 'two_factor_enabled_at', 'last_login_at', 'created_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'created_at';
        }

        // Validate direction
        $sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        $users = User::orderBy($sortField, $sortDirection)->paginate(20);

        // Preserve sort parameters in pagination links
        $users->appends(['sort' => $sortField, 'direction' => $sortDirection]);

        return view('users.index', compact('users', 'sortField', 'sortDirection'));
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        return view('users.create');
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'role' => ['required', Rule::in(['admin', 'user', 'support'])],
        ]);

        // Create user with a random temporary password (they'll set their own via email)
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(Str::random(32)), // Random placeholder password
            'role' => $validated['role'],
            'must_change_password' => true, // Ensures they can't log in until password is set
        ]);

        // Send welcome email with password setup link
        $user->notify(new WelcomeNewUser());

        return redirect()
            ->route('users.index')
            ->with('success', "User {$user->name} created successfully. A welcome email has been sent to {$user->email} with instructions to set their password.");
    }

    /**
     * Show the form for editing a user
     */
    public function edit(User $user)
    {
        return view('users.edit', compact('user'));
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'user', 'support'])],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];

        // Only update password if provided
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
            $user->must_change_password = false;
        }

        $user->save();

        return redirect()
            ->route('users.index')
            ->with('success', "User {$user->name} updated successfully.");
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user)
    {
        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return redirect()
                ->route('users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', "User {$name} deleted successfully.");
    }

    /**
     * Resend the welcome email to a user
     */
    public function resendWelcome(User $user)
    {
        // Only resend if user hasn't set their password yet
        if (!$user->must_change_password) {
            return redirect()
                ->route('users.index')
                ->with('error', "User {$user->name} has already set their password.");
        }

        $user->notify(new WelcomeNewUser());

        return redirect()
            ->route('users.index')
            ->with('success', "Welcome email resent to {$user->email}.");
    }
}
