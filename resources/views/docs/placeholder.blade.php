@extends('docs.layout')

@section('docs-content')
<h1>{{ $pageTitle }}</h1>

<div class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 my-6">
    <div class="flex">
        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
        </svg>
        <div>
            <h4 class="text-yellow-800 dark:text-yellow-200 font-medium">Documentation Coming Soon</h4>
            <p class="text-yellow-700 dark:text-yellow-300 text-sm mt-1">
                This documentation page is being written. Check back soon for complete content.
            </p>
        </div>
    </div>
</div>

<p>
    This page will contain detailed documentation about <strong>{{ $pageTitle }}</strong> in the <strong>{{ $sectionTitle }}</strong> section.
</p>

<h2>In the meantime...</h2>

<p>You can:</p>

<ul>
    <li>Browse other completed documentation sections using the sidebar</li>
    <li>Return to the <a href="{{ route('docs.index') }}">documentation home</a></li>
    <li>Contact your system administrator for immediate assistance</li>
</ul>
@endsection
