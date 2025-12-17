@extends('docs.layout')

@section('docs-content')
<h1>Remote GUI Access</h1>

<p class="lead">
    Hay ACS allows you to access a device's web interface remotely, even when you're not on the customer's network.
</p>

<h2>How It Works</h2>

<p>
    When you click the <strong>GUI</strong> button, Hay ACS:
</p>

<ol>
    <li>Temporarily sets a known password on the device</li>
    <li>Enables remote GUI access</li>
    <li>Opens the device's web interface in a new browser tab</li>
    <li>Displays the login credentials for you to use</li>
</ol>

<p>
    Access is temporary (1 hour). After this time, the password is reset to a random value for security.
</p>

<h2>Using Remote GUI</h2>

<h3>Step 1: Click the GUI Button</h3>

<p>
    From the device dashboard, click the <strong>GUI</strong> button. A new browser tab will open.
</p>

<h3>Step 2: View Credentials</h3>

<p>
    A blue credentials box will appear showing:
</p>

<ul>
    <li><strong>Username</strong> - The account to use for login</li>
    <li><strong>Password</strong> - The temporary password</li>
</ul>

<p>Click the <strong>Copy</strong> buttons to copy values to your clipboard.</p>

<h3>Step 3: Log Into the Device GUI</h3>

<p>
    In the new browser tab, enter the username and password. You'll have access to the device's full web interface.
</p>

<h3>Step 4: Close Remote Access (When Done)</h3>

<p>
    When finished, click <strong>Close Remote Access</strong> to immediately:
</p>

<ul>
    <li>Reset the password to a random value</li>
    <li>Disable remote GUI access (where supported)</li>
</ul>

<div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <p class="text-yellow-800 dark:text-yellow-200 text-sm">
            <strong>Security Note:</strong> Always close remote access when you're done. If you forget, access automatically expires after 1 hour.
        </p>
    </div>
</div>

<h2>Device-Specific Information</h2>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Device Type</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Username</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Protocol</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Calix GigaCenters (844E, 854G, 844G)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">support</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">HTTPS (port 8443)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Calix GigaSpires (GS4220E)</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">support</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">HTTP (port 8080)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Nokia Beacon 6</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">superadmin</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">HTTPS (port 443)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">SmartRG</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Device default</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">HTTPS (LAN IP)</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Troubleshooting</h2>

<h3>Page doesn't load?</h3>

<ul>
    <li>The device may be offline - check the status indicator</li>
    <li>The device may be behind NAT that blocks incoming connections</li>
    <li>Try sending a <strong>Refresh</strong> command first, then retry GUI</li>
</ul>

<h3>Password doesn't work?</h3>

<ul>
    <li>Make sure you're using the credentials shown in the blue box</li>
    <li>Try the <strong>Copy</strong> button to avoid typos</li>
    <li>Click "Close Remote Access" then "GUI" again to reset</li>
</ul>

<h3>Certificate warning?</h3>

<p>
    Some devices use self-signed SSL certificates. You may need to click "Advanced" and "Proceed" to access the site. This is expected behavior.
</p>

<h3>Connection timeout?</h3>

<p>
    The device may not be reachable. Common causes:
</p>

<ul>
    <li>Device is offline</li>
    <li>NAT/firewall blocking the connection</li>
    <li>Customer's ISP blocking remote access ports</li>
</ul>

<h2>Security Measures</h2>

<p>Remote GUI access includes several security features:</p>

<ul>
    <li><strong>Temporary access</strong> - Password automatically resets after 1 hour</li>
    <li><strong>Unique passwords</strong> - Each session uses a unique temporary password</li>
    <li><strong>Random reset</strong> - After access, password is randomized (not set to a known value)</li>
    <li><strong>Nightly audit</strong> - Any remaining remote access is disabled nightly at 10 PM</li>
    <li><strong>Logging</strong> - All GUI access is logged for audit purposes</li>
</ul>

<h2>When to Use Remote GUI</h2>

<p>Remote GUI is useful for:</p>

<ul>
    <li>Advanced configuration not available in Hay ACS</li>
    <li>Viewing device diagnostic pages</li>
    <li>Troubleshooting complex issues</li>
    <li>Accessing manufacturer-specific features</li>
</ul>

<p>For common tasks like WiFi changes, speed tests, and reboots, use the Hay ACS interface instead - it's faster and doesn't require manual credential entry.</p>
@endsection
