@extends('docs.layout')

@section('docs-content')
<h1>Trusted Devices Management</h1>

<p class="lead">
    Monitor and manage the 2FA trusted device tokens that allow users to skip 2FA verification for 90 days.
</p>

<h2>Understanding Trusted Devices</h2>

<p>
    When a user enables "Trust this device for 90 days" during 2FA verification, a token is stored that allows them to bypass 2FA on that specific browser/device combination.
</p>

<h3>How Trust Tokens Work</h3>

<ol>
    <li>User logs in with email and password</li>
    <li>User enters 2FA code from authenticator app</li>
    <li>User checks "Trust this device for 90 days"</li>
    <li>A secure token is stored in the browser cookie</li>
    <li>For 90 days, that browser can skip 2FA</li>
    <li>After 90 days, the token expires and 2FA is required again</li>
</ol>

<h2>Accessing Trusted Devices Admin</h2>

<p>Navigate to <strong>Admin</strong> &rarr; <strong>Trusted Devices</strong> in the navigation menu.</p>

<h2>Trusted Devices List</h2>

<p>The admin view shows all trusted device tokens across all users:</p>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Column</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Description</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">User</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">The user who created this trust token</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Device/Browser</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Browser and OS information (e.g., Chrome on Windows)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">IP Address</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">IP address when trust was established</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Created</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">When the trust token was created</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Expires</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">When the trust token will expire (90 days from creation)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Last Used</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">When this token was last used to bypass 2FA</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Filtering and Searching</h2>

<p>Use the filters to find specific tokens:</p>

<ul>
    <li><strong>Search</strong> - Find by user name, email, or IP address</li>
    <li><strong>User</strong> - Filter to a specific user</li>
    <li><strong>Status</strong> - Show active, expired, or all tokens</li>
</ul>

<h2>Revoking Trust Tokens</h2>

<p>To revoke a trusted device token:</p>

<ol>
    <li>Find the token in the list</li>
    <li>Click the <strong>Revoke</strong> button</li>
    <li>Confirm the revocation</li>
</ol>

<p>After revocation:</p>
<ul>
    <li>The user will be required to enter their 2FA code on next login from that device</li>
    <li>They can choose to trust the device again if they wish</li>
</ul>

<h3>Bulk Revocation</h3>

<p>To revoke all trusted devices for a specific user:</p>

<ol>
    <li>Filter the list to show only that user's tokens</li>
    <li>Click <strong>Revoke All</strong> for that user</li>
    <li>Confirm the bulk revocation</li>
</ol>

<h2>When to Revoke Trust Tokens</h2>

<p>Consider revoking trust tokens when:</p>

<ul>
    <li><strong>Device stolen or lost</strong> - User reports a laptop or phone was stolen</li>
    <li><strong>Security incident</strong> - Suspected unauthorized access</li>
    <li><strong>Employee termination</strong> - User leaving the organization</li>
    <li><strong>IP address suspicious</strong> - Trust established from unexpected location</li>
    <li><strong>Routine security audit</strong> - Periodic token cleanup</li>
</ul>

<div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <p class="text-yellow-800 dark:text-yellow-200 text-sm">
            <strong>Security Tip:</strong> For suspected account compromise, revoke all trusted devices AND reset the user's 2FA to force complete re-authentication.
        </p>
    </div>
</div>

<h2>Security Considerations</h2>

<h3>Multiple Trusted Devices</h3>

<p>
    Users may have multiple trusted devices (work computer, home computer, phone). This is normal. Review if a user has an unusually high number of trusted devices.
</p>

<h3>IP Address Patterns</h3>

<p>
    Trust tokens record the IP address when created. Look for:
</p>

<ul>
    <li><strong>Normal</strong>: Office IP addresses, known remote locations</li>
    <li><strong>Suspicious</strong>: Foreign IPs, VPN/proxy addresses, multiple IPs in short time</li>
</ul>

<h3>Token Age</h3>

<p>
    Tokens automatically expire after 90 days. However, if a user frequently re-trusts the same device, they may have very recent tokens. This is normal behavior.
</p>

<h2>Best Practices</h2>

<ul>
    <li><strong>Regular audits</strong> - Review trusted devices monthly</li>
    <li><strong>User awareness</strong> - Train users to only trust known devices</li>
    <li><strong>Incident response</strong> - Include token revocation in security procedures</li>
    <li><strong>Offboarding</strong> - Revoke tokens when users leave</li>
</ul>

<h2>Related Topics</h2>

<ul>
    <li><a href="{{ route('docs.show', ['section' => 'getting-started', 'page' => 'two-factor-auth']) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Two-Factor Authentication (User Guide)</a></li>
    <li><a href="{{ route('docs.show', ['section' => 'getting-started', 'page' => 'trusted-device']) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Trust This Device (User Guide)</a></li>
    <li><a href="{{ route('docs.show', ['section' => 'admin', 'page' => 'users']) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">User Management</a></li>
</ul>
@endsection
