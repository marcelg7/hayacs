@extends('layouts.app')

@section('title', $feedback->title . ' - Feedback - Hay ACS')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Back Link -->
    <div class="mb-6">
        <a href="{{ route('feedback.index') }}" class="text-blue-600 dark:text-blue-400 hover:underline">&larr; Back to Feedback</a>
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

    <!-- Main Feedback Card -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center space-x-2 mb-2">
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
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $feedback->title }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Submitted by {{ $feedback->user->name }} &bull; {{ $feedback->created_at->format('M j, Y \a\t g:i A') }}
                        @if($feedback->resolved_at)
                            &bull; Resolved {{ $feedback->resolved_at->diffForHumans() }}
                        @endif
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <!-- Upvote Button -->
                    <form action="{{ route('feedback.upvote', $feedback) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-3 py-2 border {{ $hasUpvoted ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300' : 'border-gray-300 dark:border-slate-600 text-gray-700 dark:text-gray-300' }} rounded-md hover:bg-gray-50 dark:hover:bg-slate-700">
                            <svg class="h-5 w-5 mr-1" fill="{{ $hasUpvoted ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                            </svg>
                            <span class="font-medium">{{ $feedback->upvotes }}</span>
                        </button>
                    </form>

                    @if(Auth::user()->isAdmin())
                        <!-- Delete Button -->
                        <form action="{{ route('feedback.destroy', $feedback) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center px-3 py-2 border border-red-300 dark:border-red-600 rounded-md text-red-700 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- Description -->
        <div class="px-6 py-4 prose dark:prose-invert max-w-none">
            {!! $feedback->description !!}
        </div>

        <!-- Admin/Support Actions -->
        @if(Auth::user()->isAdminOrSupport())
            <div class="px-6 py-4 bg-gray-50 dark:bg-slate-900/50 border-t border-gray-200 dark:border-slate-700">
                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Admin Actions</h3>
                <form action="{{ route('feedback.update', $feedback) }}" method="POST" class="flex flex-wrap gap-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="status" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                        <select name="status" id="status" class="rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                            @foreach($statuses as $key => $label)
                                <option value="{{ $key }}" {{ $feedback->status === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="priority" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Priority</label>
                        <select name="priority" id="priority" class="rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                            @foreach($priorities as $key => $label)
                                <option value="{{ $key }}" {{ $feedback->priority === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="assigned_to" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Assigned To</label>
                        <select name="assigned_to" id="assigned_to" class="rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                            <option value="">Unassigned</option>
                            @foreach($assignableUsers as $user)
                                <option value="{{ $user->id }}" {{ $feedback->assigned_to === $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                            Update
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <!-- Comments Section -->
    <div class="bg-white dark:bg-slate-800 rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-slate-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Comments ({{ $feedback->comments->count() }})
            </h2>
        </div>

        <!-- Comment List -->
        <div class="divide-y divide-gray-200 dark:divide-slate-700">
            @forelse($feedback->rootComments as $comment)
                @include('feedback.partials.comment', ['comment' => $comment, 'depth' => 0])
            @empty
                <div class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <p class="mt-2">No comments yet. Be the first to comment!</p>
                </div>
            @endforelse
        </div>

        <!-- Add Comment Form -->
        <div class="px-6 py-4 bg-gray-50 dark:bg-slate-900/50 border-t border-gray-200 dark:border-slate-700">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Add a Comment</h3>
            <form id="comment-form" action="{{ route('feedback.comment', $feedback) }}" method="POST">
                @csrf
                <textarea name="content" id="comment-content" rows="4"
                    class="tinymce-editor w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    placeholder="Write your comment..."></textarea>
                <p id="comment-error" class="text-red-500 text-sm mt-1 hidden">Please enter a comment.</p>
                @error('content')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Post Comment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- TinyMCE (self-hosted via jsDelivr - no API key required) -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.classList.contains('dark');

    tinymce.init({
        selector: '.tinymce-editor',
        height: 200,
        menubar: false,
        skin: isDark ? 'oxide-dark' : 'oxide',
        content_css: isDark ? 'dark' : 'default',
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'charmap',
            'searchreplace', 'visualblocks', 'code',
            'insertdatetime', 'table', 'wordcount', 'codesample'
        ],
        toolbar: 'undo redo | bold italic | bullist numlist | codesample | removeformat',
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; font-size: 14px; }',
        codesample_languages: [
            { text: 'PHP', value: 'php' },
            { text: 'JavaScript', value: 'javascript' },
            { text: 'HTML/XML', value: 'markup' },
            { text: 'CSS', value: 'css' },
            { text: 'Bash', value: 'bash' },
            { text: 'SQL', value: 'sql' },
            { text: 'JSON', value: 'json' },
        ],
    });

    // Handle comment form submission with validation
    const commentForm = document.getElementById('comment-form');
    const commentError = document.getElementById('comment-error');

    if (commentForm) {
        commentForm.addEventListener('submit', function(e) {
            // Sync TinyMCE content to textarea
            if (tinymce.activeEditor) {
                tinymce.activeEditor.save();
            }

            // Get content and strip HTML for validation
            const textarea = document.getElementById('comment-content');
            const content = textarea.value;
            const strippedContent = content.replace(/<[^>]*>/g, '').trim();

            // Validate content is not empty
            if (!strippedContent) {
                e.preventDefault();
                commentError.classList.remove('hidden');
                return false;
            }

            commentError.classList.add('hidden');
            return true;
        });
    }
});
</script>
@endsection
