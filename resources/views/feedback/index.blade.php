@extends('layouts.app')

@section('title', 'Feedback - Hay ACS')

@section('content')
<div class="space-y-6" x-data="{ showFilters: {{ request()->hasAny(['type', 'status', 'priority', 'search']) ? 'true' : 'false' }} }">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 dark:text-white sm:text-3xl sm:truncate">
                Feedback & Issues
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $feedbacks->total() }} items found
            </p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4 space-x-2">
            @if($unreadNotifications > 0)
                <a href="{{ route('feedback.notifications') }}" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    Notifications
                    <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-white bg-red-600 rounded-full">
                        {{ $unreadNotifications }}
                    </span>
                </a>
            @else
                <a href="{{ route('feedback.notifications') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    Notifications
                </a>
            @endif
            <button @click="showFilters = !showFilters" type="button" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                Filters
                @if(request()->hasAny(['type', 'status', 'priority', 'search']))
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Active</span>
                @endif
            </button>
            <a href="{{ route('feedback.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                New Feedback
            </a>
        </div>
    </div>

    <!-- Filters Panel -->
    <div x-show="showFilters" x-collapse class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <form method="GET" action="{{ route('feedback.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <!-- Search -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Search title or description..." class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                </div>

                <!-- Type -->
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                    <select name="type" id="type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">All Types</option>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" {{ request('status') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Priority -->
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Priority</label>
                    <select name="priority" id="priority" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="">All Priorities</option>
                        @foreach($priorities as $key => $label)
                            <option value="{{ $key }}" {{ request('priority') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="flex justify-end space-x-2">
                <a href="{{ route('feedback.index') }}" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Clear
                </a>
                <button type="submit" class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="rounded-md bg-green-50 dark:bg-green-900/50 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800 dark:text-green-200">{{ session('success') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Feedback List -->
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
            @forelse($feedbacks as $feedback)
                <li class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <a href="{{ route('feedback.show', $feedback) }}" class="block px-6 py-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center space-x-2">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $feedback->type_badge_class }}">
                                        {{ $feedback->type_label }}
                                    </span>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $feedback->status_badge_class }}">
                                        {{ $feedback->status_label }}
                                    </span>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $feedback->priority_badge_class }}">
                                        {{ $feedback->priority_label }}
                                    </span>
                                </div>
                                <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white truncate">
                                    {{ $feedback->title }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 line-clamp-2">
                                    {!! Str::limit(strip_tags($feedback->description), 200) !!}
                                </p>
                                <div class="mt-2 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                                    <span>By {{ $feedback->user->name }}</span>
                                    <span>&bull;</span>
                                    <span>{{ $feedback->created_at->diffForHumans() }}</span>
                                    @if($feedback->comments_count > 0)
                                        <span>&bull;</span>
                                        <span class="inline-flex items-center">
                                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                            </svg>
                                            {{ $feedback->comments_count }} {{ Str::plural('comment', $feedback->comments_count) }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <div class="ml-4 flex flex-col items-end space-y-2">
                                <div class="flex items-center text-gray-500 dark:text-gray-400">
                                    <svg class="h-5 w-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                    </svg>
                                    <span class="text-sm font-medium">{{ $feedback->upvotes }}</span>
                                </div>
                                @if($feedback->assignee)
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        Assigned: {{ $feedback->assignee->name }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </a>
                </li>
            @empty
                <li class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No feedback found.</p>
                    @if(request()->hasAny(['type', 'status', 'priority', 'search']))
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your filters.</p>
                        <a href="{{ route('feedback.index') }}" class="mt-2 inline-flex items-center text-sm text-blue-600 hover:text-blue-500">
                            Clear all filters
                        </a>
                    @else
                        <a href="{{ route('feedback.create') }}" class="mt-2 inline-flex items-center text-sm text-blue-600 hover:text-blue-500">
                            Submit the first feedback
                        </a>
                    @endif
                </li>
            @endforelse
        </ul>
    </div>

    <!-- Pagination -->
    @if($feedbacks->hasPages())
        <div class="bg-white dark:bg-gray-800 px-4 py-3 rounded-lg shadow">
            {{ $feedbacks->links() }}
        </div>
    @endif
</div>
@endsection
