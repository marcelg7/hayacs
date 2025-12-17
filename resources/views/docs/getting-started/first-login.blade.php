@extends('docs.layout')

@section('docs-content')
<h1>First Login & Password Setup</h1>

<p class="lead">
    When your account is created, you'll receive temporary credentials. Here's how to log in and set up your permanent password.
</p>

<h2>Step 1: Connect to VPN</h2>

<p>
    Before accessing Hay ACS, you must be connected to the corporate VPN. This is required for your initial login and whenever you don't have a trusted device set up.
</p>

<div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-blue-800 dark:text-blue-200 text-sm">
            <strong>Note:</strong> After setting up 2FA and trusted device, you won't need VPN for future logins from that device.
        </p>
    </div>
</div>

<h2>Step 2: Navigate to Hay ACS</h2>

<p>Open your web browser and go to:</p>

<div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4 my-4 font-mono text-sm">
    https://hayacs.hay.net
</div>

<h2>Step 3: Enter Your Credentials</h2>

<p>You'll see the login page. Enter the credentials provided by your administrator:</p>

<ul>
    <li><strong>Email:</strong> Your work email address</li>
    <li><strong>Password:</strong> The temporary password you were given</li>
</ul>

<p>Click the <strong>Log in</strong> button.</p>

<h2>Step 4: Change Your Password</h2>

<p>
    If this is your first login, you'll be redirected to the password change page. This is a required security step.
</p>

<h3>Password Requirements</h3>

<ul>
    <li>Minimum 8 characters</li>
    <li>Must be different from the temporary password</li>
    <li>We recommend using a mix of letters, numbers, and symbols</li>
</ul>

<h3>Setting Your New Password</h3>

<ol>
    <li>Enter your new password in the <strong>New Password</strong> field</li>
    <li>Re-enter it in the <strong>Confirm Password</strong> field</li>
    <li>Click <strong>Change Password</strong></li>
</ol>

<div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-green-800 dark:text-green-200 text-sm">
            <strong>Tip:</strong> Use a password manager to generate and store a strong, unique password.
        </p>
    </div>
</div>

<h2>Step 5: Set Up Two-Factor Authentication</h2>

<p>
    After changing your password, you'll be prompted to set up two-factor authentication (2FA). You have a 14-day grace period to complete this, but we recommend doing it immediately.
</p>

<p>
    See <a href="{{ route('docs.show', ['section' => 'getting-started', 'page' => 'two-factor-auth']) }}">Two-Factor Authentication</a> for detailed setup instructions.
</p>

<h2>Troubleshooting</h2>

<h3>Can't access the login page?</h3>

<ul>
    <li>Make sure you're connected to the corporate VPN</li>
    <li>Try clearing your browser cache</li>
    <li>Try a different browser</li>
</ul>

<h3>Password not working?</h3>

<ul>
    <li>Check that Caps Lock is off</li>
    <li>Ensure you're using the exact temporary password provided</li>
    <li>Contact your administrator if issues persist</li>
</ul>

<h3>"Session Expired" error?</h3>

<p>
    If you see a "419 - Session Expired" error, your browser session timed out. Simply click the link to return to the login page and try again. This commonly happens if the login page is left open for an extended period.
</p>

<h3>Session Timeout</h3>

<p>
    Your login session lasts for <strong>8 hours</strong> of inactivity. After 8 hours without any page activity, you'll be asked to log in again. Each page load or action resets this timer.
</p>

<p>
    <strong>Note:</strong> Field technicians using the <a href="{{ route('docs.show', ['section' => 'getting-started', 'page' => 'trusted-device']) }}">Trusted Device</a> feature will still need to enter their password after session expiration, but can skip 2FA verification.
</p>

<h2>Next Steps</h2>

<p>Once logged in with your new password, proceed to:</p>

<ol>
    <li><a href="{{ route('docs.show', ['section' => 'getting-started', 'page' => 'two-factor-auth']) }}">Set up Two-Factor Authentication</a></li>
    <li><a href="{{ route('docs.show', ['section' => 'getting-started', 'page' => 'trusted-device']) }}">Enable Trusted Device</a> (recommended for field technicians)</li>
</ol>
@endsection
