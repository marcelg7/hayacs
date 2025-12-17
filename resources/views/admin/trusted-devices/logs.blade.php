<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Trusted Device Activity Logs') }}
            </h2>
            <a href="{{ route('admin.trusted-devices.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                &larr; Back to Devices
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Filters --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6 p-4">
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">User</label>
                        <select name="user_id" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm">
                            <option value="">All Users</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ $currentUserId == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Action</label>
                        <select name="action" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm">
                            <option value="">All Actions</option>
                            @foreach($actionTypes as $action)
                                <option value="{{ $action }}" {{ $currentAction === $action ? 'selected' : '' }}>
                                    {{ str_replace('_', ' ', ucfirst($action)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fingerprint</label>
                        <select name="fingerprint_match" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm">
                            <option value="">Any</option>
                            <option value="1" {{ $currentFingerprintMatch === '1' ? 'selected' : '' }}>Matched</option>
                            <option value="0" {{ $currentFingerprintMatch === '0' ? 'selected' : '' }}>Changed</option>
                        </select>
                    </div>
                    <div>
                        <x-primary-button type="submit">Filter</x-primary-button>
                    </div>
                    @if($currentUserId || $currentAction || $currentFingerprintMatch !== null)
                        <div>
                            <a href="{{ route('admin.trusted-devices.logs') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">
                                Clear Filters
                            </a>
                        </div>
                    @endif
                </form>
            </div>

            {{-- Logs Table --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Device</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IP Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Fingerprint</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($logs as $log)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $log->created_at->format('M j, Y') }}
                                        <br>
                                        <span class="text-xs">{{ $log->created_at->format('g:i A') }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $log->trustedDevice?->user?->name ?? 'Unknown' }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $log->trustedDevice?->user?->email ?? '' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($log->trustedDevice)
                                            <a href="{{ route('admin.trusted-devices.show', $log->trustedDevice) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                                                {{ $log->trustedDevice->device_name ?? 'Unknown Device' }}
                                            </a>
                                        @else
                                            <span class="text-sm text-gray-500 dark:text-gray-400">Deleted</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full
                                            @if($log->action === 'login_bypass') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200
                                            @elseif($log->action === 'two_fa_skip') bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                                            @elseif($log->action === 'created') bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200
                                            @elseif($log->action === 'revoked') bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200
                                            @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200
                                            @endif">
                                            {{ str_replace('_', ' ', ucfirst($log->action)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500 dark:text-gray-400">
                                        {{ $log->ip_address ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($log->fingerprint_matched === true)
                                            <span class="px-2 py-1 text-xs rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                Matched
                                            </span>
                                        @elseif($log->fingerprint_matched === false)
                                            <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                                Changed
                                            </span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        No activity logs found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                @if($logs->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>

            {{-- Legend --}}
            <div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Action Legend</h4>
                <div class="flex flex-wrap gap-4 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200">Created</span>
                        <span class="text-gray-500 dark:text-gray-400">Device was trusted</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">Login Bypass</span>
                        <span class="text-gray-500 dark:text-gray-400">IP restriction bypassed</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">2FA Skip</span>
                        <span class="text-gray-500 dark:text-gray-400">2FA challenge skipped</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">Revoked</span>
                        <span class="text-gray-500 dark:text-gray-400">Device trust removed</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
