@php
    $maxDepth = 5;
    $marginLeft = min($depth * 2, $maxDepth * 2);
@endphp

<div class="px-6 py-4 {{ $depth > 0 ? 'border-l-2 border-blue-200 dark:border-blue-800' : '' }}" style="margin-left: {{ $marginLeft }}rem;">
    <div class="flex items-start space-x-3">
        <!-- Avatar -->
        <div class="flex-shrink-0">
            <div class="w-10 h-10 rounded-full {{ $comment->is_staff_response ? 'bg-blue-500' : 'bg-gray-300 dark:bg-slate-600' }} flex items-center justify-center text-white font-semibold">
                {{ strtoupper(substr($comment->user->name, 0, 1)) }}
            </div>
        </div>

        <!-- Comment Content -->
        <div class="flex-1 min-w-0">
            <div class="flex items-center space-x-2">
                <span class="font-medium text-gray-900 dark:text-white">{{ $comment->user->name }}</span>
                @if($comment->is_staff_response)
                    <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">Staff</span>
                @endif
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
            </div>

            <div class="mt-2 prose dark:prose-invert prose-sm max-w-none">
                {!! $comment->content !!}
            </div>

            <!-- Reply Button -->
            @if($depth < $maxDepth)
                <div class="mt-2" x-data="{ showReplyForm: false }">
                    <button @click="showReplyForm = !showReplyForm" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                        <span x-show="!showReplyForm">Reply</span>
                        <span x-show="showReplyForm">Cancel</span>
                    </button>

                    <!-- Reply Form -->
                    <div x-show="showReplyForm" x-collapse class="mt-3">
                        <form action="{{ route('feedback.comment', $comment->feedback) }}" method="POST">
                            @csrf
                            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                            <textarea name="content" rows="3" required
                                class="w-full rounded-md border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                                placeholder="Write a reply..."></textarea>
                            <div class="mt-2 flex justify-end">
                                <button type="submit" class="px-3 py-1.5 bg-blue-600 text-white rounded-md hover:bg-blue-700 text-sm">
                                    Reply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Render Replies Recursively --}}
@if($comment->allReplies && $comment->allReplies->count() > 0)
    @foreach($comment->allReplies as $reply)
        @include('feedback.partials.comment', ['comment' => $reply, 'depth' => $depth + 1])
    @endforeach
@endif
