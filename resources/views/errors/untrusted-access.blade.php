<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Denied - Hay ACS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-gray-100 dark:bg-gray-900 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full px-6 py-8 bg-white dark:bg-gray-800 shadow-md rounded-lg text-center">
        <div class="mb-6">
            <svg class="mx-auto h-16 w-16 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">Access Denied</h1>

        <p class="text-gray-600 dark:text-gray-400 mb-6">
            {{ $message ?? 'You must either connect via VPN or use a trusted device to access Hay ACS.' }}
        </p>

        <div class="space-y-4">
            <div class="text-left bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-2">To gain access:</h3>
                <ol class="list-decimal list-inside space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <li>Connect to the Hay VPN</li>
                    <li>Log in to Hay ACS with your credentials</li>
                    <li>After completing 2FA, choose "Trust this device"</li>
                    <li>Future logins from this device won't require VPN</li>
                </ol>
            </div>

            <p class="text-sm text-gray-500 dark:text-gray-500">
                Your IP address: <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">{{ request()->ip() }}</code>
            </p>

            <a href="{{ route('login') }}" class="inline-block w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Go to Login
            </a>
        </div>
    </div>
</body>
</html>
