@extends('docs.layout')

@section('docs-content')
<h1>Trust This Device (90-Day)</h1>

<p class="lead">
    The trusted device feature allows field technicians to access Hay ACS without VPN or 2FA codes after an initial setup. Once configured, your device is trusted for 90 days.
</p>

<h2>Why Use Trusted Device?</h2>

<p>
    Without trusted device, accessing Hay ACS requires:
</p>

<ol>
    <li>Connecting to VPN</li>
    <li>Completing VPN 2FA</li>
    <li>Logging into Hay ACS</li>
    <li>Completing Hay ACS 2FA</li>
</ol>

<p>
    That's <strong>four steps</strong> just to check a device status. With trusted device enabled, you simply:
</p>

<ol>
    <li>Open Hay ACS</li>
    <li>Log in with email and password</li>
</ol>

<p>No VPN required. No 2FA code required. Access from anywhere.</p>

<div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-green-800 dark:text-green-200 text-sm">
            <strong>Perfect for field technicians</strong> who need quick access to device information while on customer sites.
        </p>
    </div>
</div>

<h2>Initial Setup (One-Time)</h2>

<div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
        </svg>
        <p class="text-blue-800 dark:text-blue-200 text-sm">
            <strong>Important:</strong> You must be on VPN for the initial setup. This is a one-time requirement.
        </p>
    </div>
</div>

<h3>Step 1: Connect to VPN</h3>

<p>Connect to the corporate VPN as you normally would.</p>

<h3>Step 2: Go to Hay ACS</h3>

<p>Open your browser and navigate to:</p>

<div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4 my-4 font-mono text-sm">
    https://hayacs.hay.net
</div>

<h3>Step 3: Log In</h3>

<p>Enter your email and password, then click <strong>Log in</strong>.</p>

<h3>Step 4: Enter Your 2FA Code</h3>

<p>Open your authenticator app and enter the 6-digit code.</p>

<h3>Step 5: Check "Trust this device"</h3>

<p>
    <strong>Before clicking Verify</strong>, check the box labeled:
</p>

<div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-4 my-4">
    <label class="flex items-center text-gray-900 dark:text-white">
        <input type="checkbox" checked class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 mr-2" disabled>
        Trust this device for 90 days
    </label>
</div>

<h3>Step 6: Click Verify</h3>

<p>
    Click <strong>Verify</strong>. Your device is now trusted for 90 days. A secure cookie has been saved in your browser.
</p>

<h2>After Setup: Using Trusted Device</h2>

<h3>On VPN (Office)</h3>

<p>Login works immediately - no 2FA code needed. The cookie identifies your trusted device.</p>

<h3>Off VPN (Field)</h3>

<p>
    Login <strong>also works immediately</strong> - no VPN needed, no 2FA code needed. The trusted device cookie grants you access.
</p>

<h2>What Gets Trusted?</h2>

<p>The trust is <strong>per browser</strong>, not per computer. This means:</p>

<ul>
    <li>If you use Firefox at the office and Chrome in the field, you need to set up <strong>both browsers</strong></li>
    <li>Each browser stores its own trusted device cookie</li>
    <li>Trust works on any device - laptop, phone, tablet</li>
</ul>

<h2>When Trust Expires</h2>

<p>After 90 days, the trust expires. When logging in, you'll be prompted for your 2FA code again. Simply:</p>

<ol>
    <li>Connect to VPN (one time)</li>
    <li>Log in and enter your 2FA code</li>
    <li>Check "Trust this device for 90 days" again</li>
</ol>

<p>Your device is re-trusted for another 90 days.</p>

<h2>Troubleshooting</h2>

<h3>Being asked for 2FA unexpectedly?</h3>

<p>The most common causes are:</p>

<ul>
    <li><strong>Cookies were cleared</strong> - Browser cleanup or "clear history" removes the trusted device cookie</li>
    <li><strong>Different browser</strong> - Trust is per-browser; you may have set up a different browser</li>
    <li><strong>90 days passed</strong> - Trust expired; re-enable it by checking the box again</li>
    <li><strong>Private/incognito mode</strong> - Private browsing doesn't save cookies</li>
</ul>

<h3>How to Re-Enable Trust</h3>

<ol>
    <li>Connect to VPN</li>
    <li>Clear your browser cookies for hayacs.hay.net (optional but recommended)</li>
    <li>Log in to Hay ACS</li>
    <li>Enter your 2FA code</li>
    <li>Check "Trust this device for 90 days"</li>
    <li>Click Verify</li>
</ol>

<h2>Security Information</h2>

<p>The trusted device feature uses several security measures:</p>

<ul>
    <li><strong>Secure cookie</strong> - The cookie is encrypted, httpOnly (can't be read by JavaScript), and requires HTTPS</li>
    <li><strong>User-specific</strong> - The token is tied to your account; it won't work for other users</li>
    <li><strong>Device fingerprint</strong> - A hash of your browser characteristics is verified</li>
    <li><strong>Admin revocation</strong> - Administrators can revoke any trusted device at any time</li>
    <li><strong>Auto-expiration</strong> - Trust automatically expires after 90 days</li>
</ul>

@if(Auth::user()->isAdmin())
<h2>Administrator: Managing Trusted Devices</h2>

<p>
    As an administrator, you can view and revoke trusted devices from the <a href="{{ route('docs.show', ['section' => 'admin', 'page' => 'trusted-devices']) }}">Trusted Devices Admin</a> page.
</p>
@endif

<h2>Quick Reference Card</h2>

<div class="not-prose bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6 my-6">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Trusted Device Setup</h3>

    <div class="space-y-4">
        <div>
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase">Initial Setup (On VPN)</h4>
            <ol class="mt-2 text-sm text-gray-600 dark:text-gray-400 list-decimal list-inside space-y-1">
                <li>Connect to VPN</li>
                <li>Go to https://hayacs.hay.net</li>
                <li>Log in with email and password</li>
                <li>Enter 2FA code from authenticator app</li>
                <li>Check "Trust this device for 90 days"</li>
                <li>Click Verify</li>
            </ol>
        </div>

        <div>
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase">After Setup</h4>
            <ul class="mt-2 text-sm text-gray-600 dark:text-gray-400 list-disc list-inside space-y-1">
                <li>On VPN: Login works immediately (no 2FA needed)</li>
                <li>Off VPN (field): Login works immediately (no VPN or 2FA needed)</li>
            </ul>
        </div>

        <div>
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase">Remember</h4>
            <ul class="mt-2 text-sm text-gray-600 dark:text-gray-400 list-disc list-inside space-y-1">
                <li>Trust is per browser - set up each browser you use</li>
                <li>Trust lasts 90 days - check the box again when prompted</li>
                <li>Clearing cookies removes trust - set up again if needed</li>
                <li>Works on phones and tablets too</li>
            </ul>
        </div>
    </div>
</div>
@endsection
