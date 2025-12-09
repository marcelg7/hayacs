@extends('layouts.app')

@section('title', 'Notifications - Feedback - Hay ACS')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 dark:text-white sm:text-3xl sm:truncate">
                Notifications
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $notifications->total() }} notification(s)
            </p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4 space-x-2">
            <a href="{{ route('feedback.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-slate-800 hover:bg-gray-50 dark:hover:bg-slate-700">
                &larr; Back to Feedback
            </a>
            @if($notifications->where('is_read', false)->count() > 0)
                <form action="{{ route('feedback.notifications.mark-all-read') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        Mark All as Read
                    </button>
                </form>
            @endif
        </div>
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

    <!-- Notifications List -->
    <div class="bg-white dark:bg-slate-800 shadow rounded-lg overflow-hidden">
        <ul class="divide-y divide-gray-200 dark:divide-slate-700">
            @forelse($notifications as $notification)
                <li class="{{ $notification->is_read ? 'bg-white dark:bg-slate-800' : 'bg-blue-50 dark:bg-blue-900/20' }}">
                    <form action="{{ route('feedback.notifications.mark-read', $notification) }}" method="POST" class="block hover:bg-gray-50 dark:hover:bg-slate-700">
                        @csrf
                        <button type="submit" class="w-full text-left px-6 py-4">
                            <div class="flex items-start space-x-3">
                                <!-- Icon -->
                                <div class="flex-shrink-0">
                                    <svg class="h-6 w-6 {{ $notification->color }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $notification->icon }}"></path>
                                    </svg>
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ $notification->message }}
                                    </p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Re: {{ $notification->feedback->title }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                        {{ $notification->created_at->diffForHumans() }}
                                    </p>
                                </div>

                                <!-- Unread indicator -->
                                @if(!$notification->is_read)
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex h-2 w-2 rounded-full bg-blue-600"></span>
                                    </div>
                                @endif
                            </div>
                        </button>
                    </form>
                </li>
            @empty
                <li class="px-6 py-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No notifications yet.</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        You'll receive notifications when someone responds to your feedback.
                    </p>
                </li>
            @endforelse
        </ul>
    </div>

    <!-- Pagination -->
    @if($notifications->hasPages())
        <div class="bg-white dark:bg-slate-800 px-4 py-3 rounded-lg shadow">
            {{ $notifications->links() }}
        </div>
    @endif
</div>
@endsection
