@extends('layouts.app')

@section('title', 'Device Types')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                Device Types
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                Manage device types and their firmware versions
            </p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <a href="{{ route('device-types.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                Add Device Type
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-md bg-green-50 p-4">
            <div class="flex">
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Device Types Table -->
    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Manufacturer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product Class</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Firmware</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($deviceTypes as $deviceType)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">{{ $deviceType->name }}</div>
                        @if($deviceType->description)
                            <div class="text-sm text-gray-500">{{ Str::limit($deviceType->description, 50) }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $deviceType->manufacturer ?? '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $deviceType->product_class ?? '-' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <a href="{{ route('firmware.index', $deviceType) }}" class="text-blue-600 hover:text-blue-900">
                            {{ $deviceType->firmware_count }} version{{ $deviceType->firmware_count !== 1 ? 's' : '' }}
                        </a>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="{{ route('firmware.index', $deviceType) }}" class="text-blue-600 hover:text-blue-900 mr-3">Firmware</a>
                        <a href="{{ route('device-types.edit', $deviceType) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                        <form action="{{ route('device-types.destroy', $deviceType) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this device type?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                        No device types found. <a href="{{ route('device-types.create') }}" class="text-blue-600 hover:text-blue-900">Create one now</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
