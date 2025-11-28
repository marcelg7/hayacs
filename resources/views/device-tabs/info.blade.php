{{-- Device Info Tab --}}
<div x-show="activeTab === 'info'" x-cloak class="bg-white dark:bg-{{ $colors['card'] }} shadow overflow-hidden sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-{{ $colors['text'] }}">Device Information</h3>
    </div>
    <div class="border-t border-gray-200 dark:border-{{ $colors['border'] }}">
        <dl>
            <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Device ID</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">{{ $device->id }}</dd>
            </div>
            <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Manufacturer</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">{{ $device->manufacturer ?? '-' }}</dd>
            </div>
            <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">OUI</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">{{ $device->oui ?? '-' }}</dd>
            </div>
            <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Product Class</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">{{ $device->product_class ?? '-' }}</dd>
            </div>
            <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Serial Number</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">{{ $device->serial_number ?? '-' }}</dd>
            </div>
            <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Software Version</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">{{ $device->software_version ?? '-' }}</dd>
            </div>
            <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Hardware Version</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">{{ $device->hardware_version ?? '-' }}</dd>
            </div>
            <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">IP Address</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">{{ $device->ip_address ?? '-' }}</dd>
            </div>
            <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Data Model</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">
                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        {{ $device->getDataModel() }}
                    </span>
                </dd>
            </div>
            <div class="bg-white dark:bg-{{ $colors['card'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Connection Request URL</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2 break-all">{{ $device->connection_request_url ?? '-' }}</dd>
            </div>
            <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Subscriber</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-{{ $colors['text'] }} sm:mt-0 sm:col-span-2">
                    @if($device->subscriber)
                        <a href="{{ route('subscribers.show', $device->subscriber->id) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                            {{ $device->subscriber->name }}
                        </a>
                        <span class="text-gray-500 dark:text-gray-400 ml-2">({{ $device->subscriber->account }})</span>
                        @if($device->subscriber->isCableInternet() && $device->serial_number)
                            <a href="{{ $device->subscriber->getCablePortalUrl($device->serial_number) }}"
                               target="_blank"
                               class="ml-3 inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 hover:bg-purple-200 dark:hover:bg-purple-800">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                                Cable Portal
                            </a>
                        @endif
                    @else
                        <span class="text-gray-400 dark:text-gray-500">Not linked</span>
                    @endif
                </dd>
            </div>
        </dl>
    </div>
</div>
