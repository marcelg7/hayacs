@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="mb-6 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 px-4 py-3 rounded relative">
                <strong class="font-bold">Success!</strong>
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 px-4 py-3 rounded relative">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 px-4 py-3 rounded relative">
                <strong class="font-bold">Validation Errors:</strong>
                <ul class="list-disc list-inside mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Running Import Progress -->
        @if($runningImport)
            <div class="mb-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg"
                 x-data="importProgress({{ $runningImport->id }}, {{ json_encode([
                     'status' => $runningImport->status,
                     'progress_percent' => $runningImport->progress_percent,
                     'total_rows' => $runningImport->total_rows,
                     'processed_rows' => $runningImport->processed_rows,
                     'subscribers_created' => $runningImport->subscribers_created,
                     'subscribers_updated' => $runningImport->subscribers_updated,
                     'equipment_created' => $runningImport->equipment_created,
                     'devices_linked' => $runningImport->devices_linked,
                     'message' => $runningImport->message ?? 'Starting...',
                     'is_running' => $runningImport->isRunning(),
                 ]) }})"
                 x-init="startPolling()">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold">
                            <span class="inline-flex items-center">
                                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Import in Progress
                            </span>
                        </h3>
                        <span class="text-sm text-gray-500 dark:text-gray-400" x-text="status.status"></span>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mb-4">
                        <div class="flex justify-between text-sm mb-1">
                            <span x-text="status.message || 'Processing...'"></span>
                            <span x-text="status.progress_percent + '%'"></span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4">
                            <div class="bg-blue-600 h-4 rounded-full transition-all duration-500"
                                 :style="'width: ' + status.progress_percent + '%'"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                            <span x-text="status.processed_rows.toLocaleString() + ' of ' + status.total_rows.toLocaleString() + ' rows'"></span>
                            <span>File: {{ $runningImport->filename }}</span>
                        </div>
                    </div>

                    <!-- Live Stats -->
                    <div class="grid grid-cols-4 gap-4 text-center">
                        <div class="bg-blue-50 dark:bg-blue-900 p-3 rounded-lg">
                            <div class="text-xl font-bold text-blue-600 dark:text-blue-300" x-text="status.subscribers_created.toLocaleString()"></div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">Created</div>
                        </div>
                        <div class="bg-yellow-50 dark:bg-yellow-900 p-3 rounded-lg">
                            <div class="text-xl font-bold text-yellow-600 dark:text-yellow-300" x-text="status.subscribers_updated.toLocaleString()"></div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">Updated</div>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900 p-3 rounded-lg">
                            <div class="text-xl font-bold text-green-600 dark:text-green-300" x-text="status.equipment_created.toLocaleString()"></div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">Equipment</div>
                        </div>
                        <div class="bg-purple-50 dark:bg-purple-900 p-3 rounded-lg">
                            <div class="text-xl font-bold text-purple-600 dark:text-purple-300" x-text="status.devices_linked.toLocaleString()"></div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">Linked</div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function importProgress(importId, initialStatus) {
                    return {
                        status: initialStatus,
                        polling: null,
                        startPolling() {
                            // Only poll if still running
                            if (!this.status.is_running) return;

                            this.polling = setInterval(() => {
                                if (this.status.is_running) {
                                    this.fetchStatus();
                                } else {
                                    clearInterval(this.polling);
                                    // Reload page to show final results
                                    setTimeout(() => window.location.reload(), 1000);
                                }
                            }, 2000);
                        },
                        async fetchStatus() {
                            try {
                                const response = await fetch(`/subscribers/import/status/${importId}`);
                                if (response.ok) {
                                    this.status = await response.json();
                                }
                            } catch (e) {
                                console.error('Failed to fetch import status', e);
                            }
                        }
                    }
                }
            </script>
        @endif

        <!-- Current Statistics -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h3 class="text-lg font-semibold mb-4">Current Database Statistics</h3>
                <div class="grid grid-cols-3 gap-4">
                    <div class="bg-blue-50 dark:bg-blue-900 p-4 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600 dark:text-blue-300">{{ number_format($stats['total_subscribers']) }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Subscribers</div>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900 p-4 rounded-lg">
                        <div class="text-2xl font-bold text-green-600 dark:text-green-300">{{ number_format($stats['total_equipment']) }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Equipment Records</div>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900 p-4 rounded-lg">
                        <div class="text-2xl font-bold text-purple-600 dark:text-purple-300">{{ number_format($stats['linked_devices']) }}</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Linked Devices</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Imports -->
        @if($recentImports->count() > 0)
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Recent Imports</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">File</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Created</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Updated</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Equipment</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Linked</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($recentImports as $import)
                                    <tr>
                                        <td class="px-4 py-2 text-sm whitespace-nowrap">{{ $import->created_at->format('M j, Y g:i A') }}</td>
                                        <td class="px-4 py-2 text-sm max-w-xs truncate" title="{{ $import->filename }}">{{ Str::limit($import->filename, 30) }}</td>
                                        <td class="px-4 py-2 text-sm">
                                            @if($import->status === 'completed')
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Completed</span>
                                            @elseif($import->status === 'failed')
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Failed</span>
                                            @elseif($import->status === 'processing')
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Processing</span>
                                            @else
                                                <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">{{ ucfirst($import->status) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-sm text-right">{{ number_format($import->subscribers_created) }}</td>
                                        <td class="px-4 py-2 text-sm text-right">{{ number_format($import->subscribers_updated) }}</td>
                                        <td class="px-4 py-2 text-sm text-right">{{ number_format($import->equipment_created) }}</td>
                                        <td class="px-4 py-2 text-sm text-right">{{ number_format($import->devices_linked) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- Upload Form -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold">Import Subscriber Data</h2>
                    <a href="{{ route('subscribers.index') }}" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-md">
                        Back to Subscribers
                    </a>
                </div>

                @if($runningImport)
                    <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-lg p-4 mb-6">
                        <p class="text-yellow-800 dark:text-yellow-200">
                            <strong>Import in progress.</strong> Please wait for the current import to complete before starting a new one.
                        </p>
                    </div>
                @endif

                <form action="{{ route('subscribers.import.process') }}" method="POST" enctype="multipart/form-data" x-data="{ files: [] }">
                    @csrf

                    <div class="mb-6">
                        <label class="block text-sm font-medium mb-2">
                            CSV Files from NISC Ivue
                        </label>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Upload one or more CSV export files from NISC Ivue. Supported files:
                            <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-xs">Equipment for Marcel.csv</code> or
                            <code class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-xs">Equipment for Marcel Location Based.csv</code>
                        </p>

                        <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center">
                            <input
                                type="file"
                                name="csv_files[]"
                                id="csv_files"
                                multiple
                                accept=".csv,.txt"
                                class="hidden"
                                @change="files = Array.from($event.target.files)"
                                {{ $runningImport ? 'disabled' : '' }}
                            >
                            <label for="csv_files" class="cursor-pointer {{ $runningImport ? 'opacity-50 cursor-not-allowed' : '' }}">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-500">Click to select files</span>
                                    or drag and drop
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">CSV or TXT files up to 100MB each</p>
                            </label>

                            <!-- Selected Files List -->
                            <div x-show="files.length > 0" class="mt-4">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Selected Files:</p>
                                <ul class="text-left space-y-1">
                                    <template x-for="file in files" :key="file.name">
                                        <li class="text-sm text-gray-600 dark:text-gray-400 flex items-center">
                                            <svg class="w-4 h-4 mr-2 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                            </svg>
                                            <span x-text="file.name"></span>
                                            <span class="ml-2 text-xs text-gray-500" x-text="'(' + (file.size / 1024 / 1024).toFixed(2) + ' MB)'"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="flex items-center">
                            <input type="checkbox" name="truncate" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500" onclick="return confirm('This will delete ALL existing subscriber and equipment data. Are you sure?')" {{ $runningImport ? 'disabled' : '' }}>
                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                Clear existing data before import
                                <span class="text-red-600 dark:text-red-400">(Warning: This will delete all current subscriber and equipment records)</span>
                            </span>
                        </label>
                    </div>

                    <div class="bg-blue-50 dark:bg-blue-900 p-4 rounded-lg mb-6">
                        <h4 class="font-semibold text-blue-800 dark:text-blue-200 mb-2">Import Process:</h4>
                        <ol class="list-decimal list-inside text-sm text-blue-700 dark:text-blue-300 space-y-1">
                            <li>Files are uploaded to secure storage</li>
                            <li>Import is processed in the background (no timeout)</li>
                            <li>Subscribers are created or updated (by Customer + Account)</li>
                            <li>Equipment records are imported</li>
                            <li>Devices are automatically linked by serial number</li>
                        </ol>
                    </div>

                    <div class="flex justify-between items-center">
                        <button
                            type="submit"
                            class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-md disabled:opacity-50 disabled:cursor-not-allowed"
                            :disabled="files.length === 0"
                            {{ $runningImport ? 'disabled' : '' }}
                        >
                            Upload and Import
                        </button>

                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Large imports run in background - page updates automatically
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Help Section -->
        <div class="bg-gray-50 dark:bg-gray-900 overflow-hidden shadow-sm sm:rounded-lg mt-6">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <h3 class="text-lg font-semibold mb-3">Need Help?</h3>
                <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <p><strong>Expected CSV Format:</strong> Files must contain columns: Customer, Account, Name, Serv Type, Conn Dt, Equip Item, Equip Desc, Manufacturer, Model, Serial</p>
                    <p><strong>Duplicates:</strong> Subscribers with the same Customer + Account combination will be updated, not duplicated</p>
                    <p><strong>Device Linking:</strong> Devices with matching serial numbers will be automatically linked to subscribers</p>
                    <p><strong>Command Line:</strong> You can also use <code class="bg-gray-200 dark:bg-gray-700 px-2 py-1 rounded">php artisan subscriber:import path/to/file.csv</code></p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
