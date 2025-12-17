<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Trusted Device Details') }}
            </h2>
            <a href="{{ route('admin.trusted-devices.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                &larr; Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- Success/Error Messages --}}
            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 dark:bg-green-900/50 border border-green-200 dark:border-green-700 rounded-lg">
                    <p class="text-green-800 dark:text-green-200">{{ session('success') }}</p>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Device Info Card --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Device Information</h3>

                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                <dd class="mt-1">
                                    @if($trustedDevice->revoked)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200">
                                            Revoked
                                        </span>
                                        @if($trustedDevice->revoked_at)
                                            <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">
                                                on {{ $trustedDevice->revoked_at->format('M j, Y g:i A') }}
                                            </span>
                                        @endif
                                        @if($trustedDevice->revoked_by)
                                            <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">
                                                by {{ $trustedDevice->revoked_by }}
                                            </span>
                                        @endif
                                    @elseif($trustedDevice->expires_at?->isPast())
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                            Expired
                                        </span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                            Active
                                        </span>
                                    @endif
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">User</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $trustedDevice->user->name ?? 'Unknown' }}
                                    <span class="text-gray-500 dark:text-gray-400">({{ $trustedDevice->user->email ?? '' }})</span>
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Device Name</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $trustedDevice->device_name ?? 'Unknown Device' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">IP Address (when trusted)</dt>
                                <dd class="mt-1 text-sm font-mono text-gray-900 dark:text-gray-100">
                                    {{ $trustedDevice->ip_address ?? 'N/A' }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Fingerprint Hash</dt>
                                <dd class="mt-1 text-xs font-mono text-gray-500 dark:text-gray-400 break-all">
                                    {{ $trustedDevice->fingerprint_hash }}
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Token (first 8 chars)</dt>
                                <dd class="mt-1 text-xs font-mono text-gray-500 dark:text-gray-400">
                                    {{ substr($trustedDevice->token, 0, 8) }}...
                                </dd>
                            </div>
                        </dl>

                        @if(!$trustedDevice->revoked && !$trustedDevice->expires_at?->isPast())
                            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <form method="POST" action="{{ route('admin.trusted-devices.revoke', $trustedDevice) }}"
                                      onsubmit="return confirm('Revoke this trusted device? The user will need to re-authenticate via VPN.')">
                                    @csrf
                                    @method('DELETE')
                                    <x-danger-button type="submit">
                                        Revoke This Device
                                    </x-danger-button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Timeline Card --}}
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Timeline</h3>

                        <dl class="space-y-4">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Trusted At</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $trustedDevice->trusted_at?->format('M j, Y g:i A') ?? 'N/A' }}
                                    @if($trustedDevice->trusted_at)
                                        <span class="text-gray-500 dark:text-gray-400">({{ $trustedDevice->trusted_at->diffForHumans() }})</span>
                                    @endif
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Expires At</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $trustedDevice->expires_at?->format('M j, Y g:i A') ?? 'N/A' }}
                                    @if($trustedDevice->expires_at && !$trustedDevice->expires_at->isPast())
                                        <span class="text-gray-500 dark:text-gray-400">({{ $trustedDevice->expires_at->diffForHumans() }})</span>
                                    @endif
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Last Used</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    @if($trustedDevice->last_used_at)
                                        {{ $trustedDevice->last_used_at->format('M j, Y g:i A') }}
                                        <span class="text-gray-500 dark:text-gray-400">({{ $trustedDevice->last_used_at->diffForHumans() }})</span>
                                    @else
                                        Never used
                                    @endif
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Created</dt>
                                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                                    {{ $trustedDevice->created_at?->format('M j, Y g:i A') ?? 'N/A' }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>

            {{-- Activity Logs --}}
            <div class="mt-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Activity Log</h3>

                    @if($recentLogs->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400">No activity recorded yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Time</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Action</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">IP Address</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Fingerprint</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($recentLogs as $log)
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                                {{ $log->created_at->format('M j, g:i A') }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                <span class="px-2 py-1 text-xs rounded-full
                                                    @if($log->action === 'login_bypass') bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200
                                                    @elseif($log->action === 'two_fa_skip') bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200
                                                    @elseif($log->action === 'created') bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200
                                                    @else bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200
                                                    @endif">
                                                    {{ str_replace('_', ' ', ucfirst($log->action)) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-sm font-mono text-gray-500 dark:text-gray-400">
                                                {{ $log->ip_address ?? 'N/A' }}
                                            </td>
                                            <td class="px-4 py-2 text-sm">
                                                @if($log->fingerprint_matched === true)
                                                    <span class="text-green-600 dark:text-green-400">Matched</span>
                                                @elseif($log->fingerprint_matched === false)
                                                    <span class="text-yellow-600 dark:text-yellow-400">Changed</span>
                                                @else
                                                    <span class="text-gray-400">N/A</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
