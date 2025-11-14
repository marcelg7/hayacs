@extends('layouts.app')

@section('title', 'Upload Firmware - ' . $deviceType->name)

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                Upload Firmware
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Upload new firmware for {{ $deviceType->name }}
            </p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <a href="{{ route('firmware.index', $deviceType) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Back to Firmware
            </a>
        </div>
    </div>

    <!-- Form -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <form action="{{ route('firmware.store', $deviceType) }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="px-4 py-5 sm:p-6 space-y-6">
                <!-- Version -->
                <div>
                    <label for="version" class="block text-sm font-medium text-gray-700">Version <span class="text-red-500">*</span></label>
                    <input type="text" name="version" id="version" value="{{ old('version') }}" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('version') border-red-500 @enderror">
                    @error('version')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">e.g., "1.2.3", "R2.11.8", "v3.0.1"</p>
                </div>

                <!-- Firmware File -->
                <div>
                    <label for="firmware_file" class="block text-sm font-medium text-gray-700">Firmware File <span class="text-red-500">*</span></label>
                    <input type="file" name="firmware_file" id="firmware_file" required accept=".bin,.img,.tar,.tar.gz,.zip"
                           class="mt-1 block w-full text-sm text-gray-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-md file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-blue-50 file:text-blue-700
                                  hover:file:bg-blue-100
                                  @error('firmware_file') border-red-500 @enderror">
                    @error('firmware_file')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">Maximum file size: 100MB. Supported formats: .bin, .img, .tar, .tar.gz, .zip</p>
                </div>

                <!-- Release Notes -->
                <div>
                    <label for="release_notes" class="block text-sm font-medium text-gray-700">Release Notes</label>
                    <textarea name="release_notes" id="release_notes" rows="4"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('release_notes') border-red-500 @enderror">{{ old('release_notes') }}</textarea>
                    @error('release_notes')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">Optional: What's new in this version, bug fixes, improvements, etc.</p>
                </div>

                <!-- Download URL (Optional) -->
                <div>
                    <label for="download_url" class="block text-sm font-medium text-gray-700">Custom Download URL (Optional)</label>
                    <input type="url" name="download_url" id="download_url" value="{{ old('download_url') }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm @error('download_url') border-red-500 @enderror">
                    @error('download_url')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-sm text-gray-500">Leave blank to use the uploaded file. Provide a custom URL if firmware is hosted elsewhere.</p>
                </div>

                <!-- Set as Active -->
                <div class="flex items-start">
                    <div class="flex items-center h-5">
                        <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active') ? 'checked' : '' }}
                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                    </div>
                    <div class="ml-3 text-sm">
                        <label for="is_active" class="font-medium text-gray-700">Set as active firmware version</label>
                        <p class="text-gray-500">This version will be used for firmware upgrades. Previous active version will be deactivated.</p>
                    </div>
                </div>
            </div>

            <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                <a href="{{ route('firmware.index', $deviceType) }}" class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 mr-3">
                    Cancel
                </a>
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Upload Firmware
                </button>
            </div>
        </form>
    </div>

    <!-- Information Box -->
    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3 flex-1">
                <h3 class="text-sm font-medium text-yellow-800">Important Notes</h3>
                <div class="mt-2 text-sm text-yellow-700">
                    <ul class="list-disc pl-5 space-y-1">
                        <li>Ensure the firmware file is compatible with <strong>{{ $deviceType->name }}</strong></li>
                        <li>Test firmware on a single device before deploying to production</li>
                        <li>Firmware upgrades may take several minutes and will reboot the device</li>
                        <li>Keep backup firmware versions available in case of issues</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
