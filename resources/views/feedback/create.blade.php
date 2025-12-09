@extends('layouts.app')

@section('title', 'Submit Feedback - Hay ACS')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-6">
        <a href="{{ route('feedback.index') }}" class="text-blue-600 dark:text-blue-400 hover:underline">&larr; Back to Feedback</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">Submit Feedback</h1>

        <form action="{{ route('feedback.store') }}" method="POST">
            @csrf

            <div class="space-y-6">
                <!-- Type -->
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type *</label>
                    <select name="type" id="type" required class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select type...</option>
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" {{ old('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        <span class="font-medium">Bug Report:</span> Something isn't working correctly.<br>
                        <span class="font-medium">General Feedback:</span> Suggestions, questions, or general comments.<br>
                        <span class="font-medium">Feature Request:</span> Request a new feature or enhancement.
                    </p>
                </div>

                <!-- Title -->
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title *</label>
                    <input type="text" name="title" id="title" required maxlength="255"
                        class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        value="{{ old('title') }}"
                        placeholder="Brief summary of your feedback">
                    @error('title')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Priority -->
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority *</label>
                    <select name="priority" id="priority" required class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach($priorities as $key => $label)
                            <option value="{{ $key }}" {{ old('priority', 'medium') === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('priority')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        <span class="font-medium">Low:</span> Minor issue, no urgency.<br>
                        <span class="font-medium">Medium:</span> Normal priority.<br>
                        <span class="font-medium">High:</span> Important issue affecting work.<br>
                        <span class="font-medium">Critical:</span> Urgent issue, system unusable or data loss.
                    </p>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description *</label>
                    <textarea name="description" id="description" rows="10"
                        class="tinymce-editor w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500"
                        placeholder="Provide detailed information about your feedback...">{{ old('description') }}</textarea>
                    <p id="description-error" class="text-red-500 text-sm mt-1 hidden">Please enter a description.</p>
                    @error('description')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Please include relevant details such as steps to reproduce (for bugs), expected vs actual behavior, or use case (for features).
                    </p>
                </div>

                <!-- Submit -->
                <div class="flex justify-end gap-3">
                    <a href="{{ route('feedback.index') }}"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Cancel
                    </a>
                    <button type="submit"
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Submit Feedback
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- TinyMCE (self-hosted via jsDelivr - no API key required) -->
<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.classList.contains('dark');

    tinymce.init({
        selector: '.tinymce-editor',
        height: 400,
        menubar: false,
        skin: isDark ? 'oxide-dark' : 'oxide',
        content_css: isDark ? 'dark' : 'default',
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount', 'codesample'
        ],
        toolbar: 'undo redo | blocks | ' +
            'bold italic forecolor | alignleft aligncenter ' +
            'alignright alignjustify | bullist numlist outdent indent | ' +
            'codesample | removeformat | help',
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
        setup: function(editor) {
            // Sync content to textarea on change
            editor.on('change', function() {
                editor.save();
            });
        }
    });

    // Handle form submission - validate TinyMCE content
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        // Ensure TinyMCE content is synced to textarea
        if (tinymce.activeEditor) {
            tinymce.activeEditor.save();
        }

        // Check if description is empty
        const description = document.getElementById('description').value.trim();
        const descriptionError = document.getElementById('description-error');

        // Strip HTML tags to check for actual content
        const textContent = description.replace(/<[^>]*>/g, '').trim();

        if (!textContent) {
            e.preventDefault();
            descriptionError.classList.remove('hidden');
            // Scroll to the description field
            document.getElementById('description').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        } else {
            descriptionError.classList.add('hidden');
        }
    });
});
</script>
@endsection
