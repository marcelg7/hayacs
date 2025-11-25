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
        </dl>
    </div>
</div>
