@extends('docs.layout')

@section('docs-content')
<h1>User Management</h1>

<p class="lead">
    Create and manage user accounts, assign roles, and control access to Hay ACS.
</p>

<h2>Accessing User Management</h2>

<p>Navigate to <strong>Admin</strong> &rarr; <strong>Users</strong> in the navigation menu.</p>

<h2>User Roles</h2>

<p>Hay ACS has three user roles with different permission levels:</p>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Role</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Access Level</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Typical Use</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 rounded text-sm font-medium">Admin</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Full system access including user management, firmware, workflows</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">System administrators, IT managers</td>
            </tr>
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 rounded text-sm font-medium">Support</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Device management and troubleshooting, no admin functions</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Technical support staff, field technicians</td>
            </tr>
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded text-sm font-medium">User</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Basic device viewing and analytics access</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Read-only access, reporting users</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Creating a New User</h2>

<ol>
    <li>Click <strong>Create User</strong> button</li>
    <li>Enter user details:
        <ul>
            <li><strong>Name</strong> - Full name for display</li>
            <li><strong>Email</strong> - Login email address (must be unique)</li>
            <li><strong>Role</strong> - Select appropriate role</li>
            <li><strong>Password</strong> - Temporary password</li>
        </ul>
    </li>
    <li>Enable <strong>Require password change</strong> (recommended)</li>
    <li>Click <strong>Create User</strong></li>
</ol>

<div class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <p class="text-blue-800 dark:text-blue-200 text-sm">
            <strong>Best Practice:</strong> Always enable "Require password change" so users set their own secure password on first login.
        </p>
    </div>
</div>

<h2>User List</h2>

<p>The user list shows all accounts with:</p>

<ul>
    <li><strong>Name</strong> - User's display name</li>
    <li><strong>Email</strong> - Login email</li>
    <li><strong>Role</strong> - Color-coded role badge</li>
    <li><strong>2FA Status</strong> - Whether two-factor authentication is enabled</li>
    <li><strong>Last Login</strong> - When the user last logged in</li>
    <li><strong>Actions</strong> - Edit and delete buttons</li>
</ul>

<h2>Editing a User</h2>

<p>Click the <strong>Edit</strong> button next to a user to modify:</p>

<ul>
    <li><strong>Name</strong> - Update display name</li>
    <li><strong>Email</strong> - Change login email</li>
    <li><strong>Role</strong> - Change permission level</li>
    <li><strong>Password</strong> - Set a new password (optional)</li>
    <li><strong>Require password change</strong> - Force password reset on next login</li>
</ul>

<h2>Two-Factor Authentication Management</h2>

<h3>2FA Status Indicators</h3>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Meaning</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded text-sm">Enabled</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">User has 2FA configured and active</td>
            </tr>
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300 rounded text-sm">Grace Period (X days)</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">User has not yet set up 2FA, X days remaining</td>
            </tr>
            <tr>
                <td class="px-4 py-3">
                    <span class="px-2 py-1 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 rounded text-sm">Required</span>
                </td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Grace period expired, user must set up 2FA</td>
            </tr>
        </tbody>
    </table>
</div>

<h3>Resetting a User's 2FA</h3>

<p>If a user loses access to their authenticator app:</p>

<ol>
    <li>Find the user in the user list</li>
    <li>Click the <strong>Reset 2FA</strong> button</li>
    <li>Confirm the reset</li>
</ol>

<p>This will:</p>
<ul>
    <li>Disable their current 2FA</li>
    <li>Start a new 14-day grace period</li>
    <li>Allow them to set up a new authenticator</li>
</ul>

<div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <p class="text-yellow-800 dark:text-yellow-200 text-sm">
            <strong>Security Note:</strong> Verify the user's identity before resetting 2FA. Consider requiring them to come to the office in person.
        </p>
    </div>
</div>

<h2>Deleting a User</h2>

<ol>
    <li>Click the <strong>Delete</strong> button next to the user</li>
    <li>Confirm the deletion</li>
</ol>

<div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg p-4 my-4">
    <div class="flex">
        <svg class="w-5 h-5 text-red-600 dark:text-red-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <p class="text-red-800 dark:text-red-200 text-sm">
            <strong>Warning:</strong> User deletion is permanent. You cannot delete your own account.
        </p>
    </div>
</div>

<h2>Password Requirements</h2>

<p>Passwords must meet the following requirements:</p>

<ul>
    <li>Minimum 8 characters</li>
    <li>Must be confirmed (entered twice)</li>
</ul>

<h2>Best Practices</h2>

<ul>
    <li><strong>Use descriptive names</strong> - Full names help identify users in logs</li>
    <li><strong>Assign minimum required role</strong> - Don't give admin access unless needed</li>
    <li><strong>Regular audits</strong> - Review user list quarterly for inactive accounts</li>
    <li><strong>Monitor 2FA compliance</strong> - Ensure all users have 2FA enabled</li>
    <li><strong>Document access</strong> - Keep records of who has access and why</li>
</ul>
@endsection
