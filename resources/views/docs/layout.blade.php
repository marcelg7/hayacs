@extends('layouts.app')

@push('styles')
<style>
    /* Documentation-specific styles for better readability */
    .docs-content h2:first-of-type {
        margin-top: 3rem !important;
        border-top: none !important;
        padding-top: 0 !important;
    }
    .docs-content .not-prose {
        margin-top: 2rem;
        margin-bottom: 2rem;
    }
    .docs-content .not-prose + h2 {
        margin-top: 4rem !important;
    }
    .docs-content .not-prose table {
        border-radius: 0.75rem;
        overflow: hidden;
        box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
    }

    /* Enhanced callout boxes */
    .docs-content .info-box {
        background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
        border-left: 5px solid #6366F1;
        padding: 1.25rem 1.5rem;
        border-radius: 0.75rem;
        margin: 2rem 0;
        box-shadow: 0 2px 4px rgba(99, 102, 241, 0.1);
    }
    .dark .docs-content .info-box {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(99, 102, 241, 0.1) 100%);
        border-color: #818CF8;
    }
    .docs-content .warning-box {
        background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
        border-left: 5px solid #F59E0B;
        padding: 1.25rem 1.5rem;
        border-radius: 0.75rem;
        margin: 2rem 0;
        box-shadow: 0 2px 4px rgba(245, 158, 11, 0.1);
    }
    .dark .docs-content .warning-box {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.2) 0%, rgba(245, 158, 11, 0.1) 100%);
        border-color: #FBBF24;
    }
    .docs-content .tip-box {
        background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%);
        border-left: 5px solid #10B981;
        padding: 1.25rem 1.5rem;
        border-radius: 0.75rem;
        margin: 2rem 0;
        box-shadow: 0 2px 4px rgba(16, 185, 129, 0.1);
    }
    .dark .docs-content .tip-box {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(16, 185, 129, 0.1) 100%);
        border-color: #34D399;
    }

    /* Code block styling */
    .docs-content pre {
        background-color: #1f2937 !important;
        color: #e5e7eb !important;
        padding: 1rem 1.25rem;
        border-radius: 0.5rem;
        overflow-x: auto;
        margin: 1.5rem 0;
        font-size: 0.875rem;
        line-height: 1.7;
        border: 1px solid #374151;
    }
    .docs-content pre code {
        background-color: transparent !important;
        color: inherit !important;
        padding: 0 !important;
        font-size: inherit;
        border-radius: 0;
    }
    .dark .docs-content pre {
        background-color: #111827 !important;
        border-color: #374151;
    }

    /* Better list styling */
    .docs-content ul li::marker {
        color: #6366F1;
        font-weight: bold;
    }
    .dark .docs-content ul li::marker {
        color: #818CF8;
    }

    /* Enhanced lead paragraph */
    .docs-content .lead {
        font-size: 1.375rem;
        line-height: 2;
        color: #4B5563;
        font-weight: 400;
    }
    .dark .docs-content .lead {
        color: #9CA3AF;
    }
</style>
@endpush

@section('content')
<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 flex-shrink-0 hidden lg:block">
        <div class="sticky top-0 overflow-y-auto h-screen pb-12">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                    <svg class="w-5 h-5 mr-2 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    Documentation
                </h2>
            </div>
            <nav class="p-4 space-y-6">
                @foreach($structure as $sectionKey => $section)
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2 flex items-center">
                        @include('docs.partials.icon', ['icon' => $section['icon']])
                        <span class="ml-2">{{ $section['title'] }}</span>
                        @if(isset($section['admin_only']) && $section['admin_only'])
                        <span class="ml-2 px-1.5 py-0.5 text-xs bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded">Admin</span>
                        @endif
                    </h3>
                    <ul class="space-y-1">
                        @foreach($section['pages'] as $pageKey => $pageTitle)
                        <li>
                            <a href="{{ route('docs.show', ['section' => $sectionKey, 'page' => $pageKey]) }}"
                               class="block px-3 py-2 text-sm rounded-md transition-colors
                                      {{ $currentSection === $sectionKey && $currentPage === $pageKey
                                         ? 'bg-indigo-50 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-medium'
                                         : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                                {{ $pageTitle }}
                            </a>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </nav>
        </div>
    </aside>

    <!-- Mobile sidebar toggle -->
    <div class="lg:hidden fixed bottom-4 right-4 z-50">
        <button onclick="document.getElementById('mobile-docs-nav').classList.toggle('hidden')"
                class="bg-indigo-600 text-white p-3 rounded-full shadow-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
        </button>
    </div>

    <!-- Mobile sidebar -->
    <div id="mobile-docs-nav" class="hidden lg:hidden fixed inset-0 z-40 bg-gray-900 bg-opacity-50" onclick="this.classList.add('hidden')">
        <aside class="w-72 h-full bg-white dark:bg-gray-800 overflow-y-auto" onclick="event.stopPropagation()">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Documentation</h2>
                <button onclick="document.getElementById('mobile-docs-nav').classList.add('hidden')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <nav class="p-4 space-y-6">
                @foreach($structure as $sectionKey => $section)
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">
                        {{ $section['title'] }}
                    </h3>
                    <ul class="space-y-1">
                        @foreach($section['pages'] as $pageKey => $pageTitle)
                        <li>
                            <a href="{{ route('docs.show', ['section' => $sectionKey, 'page' => $pageKey]) }}"
                               class="block px-3 py-2 text-sm rounded-md
                                      {{ $currentSection === $sectionKey && $currentPage === $pageKey
                                         ? 'bg-indigo-50 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-medium'
                                         : 'text-gray-700 dark:text-gray-300' }}">
                                {{ $pageTitle }}
                            </a>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endforeach
            </nav>
        </aside>
    </div>

    <!-- Main content -->
    <main class="flex-1 overflow-x-hidden bg-gray-50 dark:bg-gray-900">
        <div class="max-w-4xl mx-auto px-6 sm:px-8 lg:px-12 py-12">
            <!-- Breadcrumb -->
            @if($currentSection)
            <nav class="flex mb-6" aria-label="Breadcrumb">
                <ol class="flex items-center space-x-2 text-sm">
                    <li>
                        <a href="{{ route('docs.index') }}" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                            Docs
                        </a>
                    </li>
                    <li class="flex items-center">
                        <svg class="w-4 h-4 text-gray-400 mx-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <a href="{{ route('docs.show', ['section' => $currentSection, 'page' => 'index']) }}"
                           class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
                            {{ $sectionTitle }}
                        </a>
                    </li>
                    @if($currentPage !== 'index')
                    <li class="flex items-center">
                        <svg class="w-4 h-4 text-gray-400 mx-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-gray-900 dark:text-white font-medium">{{ $pageTitle }}</span>
                    </li>
                    @endif
                </ol>
            </nav>
            @endif

            <!-- Page content -->
            <article class="docs-content prose prose-lg dark:prose-invert prose-indigo max-w-none
                           prose-headings:font-bold prose-headings:tracking-tight
                           prose-h1:text-4xl prose-h1:mb-8 prose-h1:pb-6 prose-h1:border-b-2 prose-h1:border-gray-200 dark:prose-h1:border-gray-700
                           prose-h2:text-2xl prose-h2:mt-16 prose-h2:mb-6 prose-h2:pt-8 prose-h2:border-t prose-h2:border-gray-200 dark:prose-h2:border-gray-700
                           prose-h3:text-xl prose-h3:mt-10 prose-h3:mb-4
                           prose-p:text-lg prose-p:leading-8 prose-p:mb-6 prose-p:text-gray-700 dark:prose-p:text-gray-300
                           prose-ul:my-6 prose-ul:space-y-3
                           prose-ol:my-6 prose-ol:space-y-3
                           prose-li:text-lg prose-li:leading-8
                           prose-table:my-8
                           prose-a:text-indigo-600 dark:prose-a:text-indigo-400 prose-a:font-semibold prose-a:underline-offset-2
                           prose-strong:font-bold prose-strong:text-gray-900 dark:prose-strong:text-white
                           prose-code:text-base prose-code:bg-gray-100 dark:prose-code:bg-gray-800 prose-code:px-2 prose-code:py-1 prose-code:rounded-md prose-code:font-medium">
                @yield('docs-content')
            </article>

            <!-- Navigation footer -->
            @if($currentSection)
            <div class="mt-12 pt-6 border-t border-gray-200 dark:border-gray-700">
                @php
                    $pages = array_keys($structure[$currentSection]['pages']);
                    $currentIndex = array_search($currentPage, $pages);
                    $prevPage = $currentIndex > 0 ? $pages[$currentIndex - 1] : null;
                    $nextPage = $currentIndex < count($pages) - 1 ? $pages[$currentIndex + 1] : null;
                @endphp
                <div class="flex justify-between">
                    <div>
                        @if($prevPage)
                        <a href="{{ route('docs.show', ['section' => $currentSection, 'page' => $prevPage]) }}"
                           class="inline-flex items-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            {{ $structure[$currentSection]['pages'][$prevPage] }}
                        </a>
                        @endif
                    </div>
                    <div>
                        @if($nextPage)
                        <a href="{{ route('docs.show', ['section' => $currentSection, 'page' => $nextPage]) }}"
                           class="inline-flex items-center text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                            {{ $structure[$currentSection]['pages'][$nextPage] }}
                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
            @endif
        </div>
    </main>
</div>
@endsection
