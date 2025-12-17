@extends('docs.layout')

@section('docs-content')
<h1>Getting Started</h1>

<p class="lead">
    Welcome to Hay ACS! This section will guide you through your first login, security setup, and basic navigation.
</p>

<div class="info-box not-prose">
    <p class="text-gray-800 dark:text-gray-200 font-medium mb-1">New to Hay ACS?</p>
    <p class="text-gray-600 dark:text-gray-400 text-sm">Follow the Quick Start steps below to get set up in about 5 minutes.</p>
</div>

<h2>What is Hay ACS?</h2>

<p>
    Hay ACS (Auto Configuration Server) is a TR-069/CWMP management platform that allows you to remotely configure, monitor, and troubleshoot customer premises equipment (CPE) such as routers, gateways, and mesh access points.
</p>

<h2>Quick Start Steps</h2>

<div class="not-prose space-y-4">
    <div class="flex items-start p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow">
        <span class="flex-shrink-0 w-10 h-10 bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 rounded-full flex items-center justify-center font-bold mr-4 text-lg">1</span>
        <div class="flex-1">
            <a href="{{ route('docs.show', ['section' => 'getting-started', 'page' => 'first-login']) }}" class="font-semibold text-lg text-indigo-600 dark:text-indigo-400 hover:underline">First Login & Password Setup</a>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Log in with your temporary credentials and set a new secure password.</p>
        </div>
        <svg class="w-5 h-5 text-gray-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
    </div>

    <div class="flex items-start p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow">
        <span class="flex-shrink-0 w-10 h-10 bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 rounded-full flex items-center justify-center font-bold mr-4 text-lg">2</span>
        <div class="flex-1">
            <a href="{{ route('docs.show', ['section' => 'getting-started', 'page' => 'two-factor-auth']) }}" class="font-semibold text-lg text-indigo-600 dark:text-indigo-400 hover:underline">Two-Factor Authentication</a>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Set up 2FA using an authenticator app for enhanced security.</p>
        </div>
        <svg class="w-5 h-5 text-gray-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
    </div>

    <div class="flex items-start p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow">
        <span class="flex-shrink-0 w-10 h-10 bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 rounded-full flex items-center justify-center font-bold mr-4 text-lg">3</span>
        <div class="flex-1">
            <a href="{{ route('docs.show', ['section' => 'getting-started', 'page' => 'trusted-device']) }}" class="font-semibold text-lg text-indigo-600 dark:text-indigo-400 hover:underline">Trust This Device</a>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Enable 90-day trusted device for easier field access.</p>
        </div>
        <svg class="w-5 h-5 text-gray-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
    </div>

    <div class="flex items-start p-4 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow">
        <span class="flex-shrink-0 w-10 h-10 bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 rounded-full flex items-center justify-center font-bold mr-4 text-lg">4</span>
        <div class="flex-1">
            <a href="{{ route('docs.show', ['section' => 'getting-started', 'page' => 'navigation']) }}" class="font-semibold text-lg text-indigo-600 dark:text-indigo-400 hover:underline">Navigation</a>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Learn your way around the interface.</p>
        </div>
        <svg class="w-5 h-5 text-gray-400 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
    </div>
</div>

<h2>User Roles</h2>

<p>Hay ACS has three user roles with different access levels:</p>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Role</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Access Level</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Admin</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Full system access including user management, device groups, workflows, firmware, and reports</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Support</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Device management, troubleshooting, WiFi configuration, and subscriber lookup</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">User</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Basic device viewing and analytics</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Browser Requirements</h2>

<p>Hay ACS works best with modern browsers:</p>

<ul>
    <li>Mozilla Firefox</li>
    <li>Google Chrome</li>
    <li>Microsoft Edge</li>
    <li>Safari</li>
</ul>

<p>JavaScript must be enabled for full functionality.</p>

<h2>Network Requirements</h2>

<p>
    Access to Hay ACS requires either:
</p>

<ul>
    <li>Being on the corporate network (VPN or office)</li>
    <li>Having a <a href="{{ route('docs.show', ['section' => 'getting-started', 'page' => 'trusted-device']) }}">trusted device</a> set up (for field technicians)</li>
</ul>

<h2>Need Help?</h2>

<p>
    If you encounter issues during setup or have questions not covered in this documentation, contact your system administrator.
</p>
@endsection
