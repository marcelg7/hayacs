@extends('docs.layout')

@section('docs-content')
<h1>Admin Guide Overview</h1>

<p class="lead">
    This section covers administrative functions available only to users with the Admin role.
</p>

<div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <p class="text-yellow-800 dark:text-yellow-200 text-sm">
            <strong>Admin Only:</strong> The features documented in this section require the Admin role. Support and User roles do not have access to these functions.
        </p>
    </div>
</div>

<h2>Admin Responsibilities</h2>

<p>As an administrator, you are responsible for:</p>

<ul>
    <li><strong>User Management</strong> - Creating, editing, and managing user accounts</li>
    <li><strong>Security</strong> - Monitoring 2FA compliance and resetting access when needed</li>
    <li><strong>Device Groups</strong> - Organizing devices for bulk operations</li>
    <li><strong>Workflows</strong> - Scheduling and managing automated device operations</li>
    <li><strong>Firmware</strong> - Managing firmware files and deployments</li>
    <li><strong>System Tasks</strong> - Monitoring and managing the task queue</li>
</ul>

<h2>Admin Menu</h2>

<p>Admin functions are accessed through the <strong>Admin</strong> dropdown menu in the navigation bar.</p>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Menu Item</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Purpose</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Users</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Manage user accounts and permissions</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Trusted Devices</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">View and manage 2FA trusted devices</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Device Groups</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Create and manage device groups</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Workflows</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Schedule automated operations</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Firmware</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Manage firmware files</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Tasks</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">System-wide task management</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Quick Reference</h2>

<div class="not-prose space-y-4 my-6">
    <a href="{{ route('docs.show', ['section' => 'admin', 'page' => 'users']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">User Management</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Create users, assign roles, manage passwords and 2FA.</p>
    </a>
    <a href="{{ route('docs.show', ['section' => 'admin', 'page' => 'trusted-devices-admin']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Trusted Devices Management</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">View and revoke 2FA trusted device tokens.</p>
    </a>
    <a href="{{ route('docs.show', ['section' => 'admin', 'page' => 'device-groups']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Device Groups</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Organize devices for bulk operations.</p>
    </a>
    <a href="{{ route('docs.show', ['section' => 'admin', 'page' => 'workflows']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Workflows</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Schedule and automate device operations.</p>
    </a>
    <a href="{{ route('docs.show', ['section' => 'admin', 'page' => 'firmware']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Firmware Management</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Upload and deploy device firmware updates.</p>
    </a>
    <a href="{{ route('docs.show', ['section' => 'admin', 'page' => 'tasks']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Task Management</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Monitor and manage system-wide tasks.</p>
    </a>
</div>

<h2>Security Best Practices</h2>

<ul>
    <li><strong>Limit Admin Access</strong> - Only grant admin role to users who need it</li>
    <li><strong>Monitor User Activity</strong> - Regularly review user accounts and activity</li>
    <li><strong>Enforce 2FA</strong> - Ensure all users complete 2FA setup</li>
    <li><strong>Review Trusted Devices</strong> - Periodically audit trusted device tokens</li>
    <li><strong>Test Workflows</strong> - Always test workflows on small device groups first</li>
</ul>
@endsection
