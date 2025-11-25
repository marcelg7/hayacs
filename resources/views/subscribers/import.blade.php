@extends('layouts.app')

@section('content')
<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <!-- Success/Error Messages -->
        @if(session('success'))
            <div class="mb-6 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 px-4 py-3 rounded relative">
                <strong class="font-bold">Success!</strong>
                <span class="block sm:inline">{{ session('success') }}</span>

                @if(session('stats'))
                    <div class="mt-4 text-sm">
                        <strong>Import Summary:</strong>
                        <ul class="list-disc list-inside mt-2">
                            <li>Subscribers Created: {{ session('stats')['subscribers_created'] }}</li>
                            <li>Subscribers Updated: {{ session('stats')['subscribers_updated'] }}</li>
                            <li>Equipment Records: {{ session('stats')['equipment_created'] }}</li>
                            <li>Devices Linked: {{ session('stats')['devices_linked'] }}</li>
                        </ul>
                    </div>
                @endif
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

        <!-- Upload Form -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold">Import Subscriber Data</h2>
                    <a href="{{ route('subscribers.index') }}" class="px-4 py-2 bg-gray-500 hover:bg-gray-600 text-white rounded-md">
                        Back to Subscribers
                    </a>
                </div>

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
                            >
                            <label for="csv_files" class="cursor-pointer">
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
                            <input type="checkbox" name="truncate" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:ring-blue-500" onclick="return confirm('This will delete ALL existing subscriber and equipment data. Are you sure?')">
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
                            <li>CSV data is parsed and validated</li>
                            <li>Subscribers are created or updated (by Customer + Account)</li>
                            <li>Equipment records are imported</li>
                            <li>Devices are automatically linked by serial number</li>
                        </ol>
                    </div>

                    <div class="flex justify-between items-center">
                        <button
                            type="submit"
                            class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-md disabled:opacity-50"
                            :disabled="files.length === 0"
                        >
                            Upload and Import
                        </button>

                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Large imports may take several minutes
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
