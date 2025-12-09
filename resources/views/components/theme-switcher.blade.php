<div x-data="{ open: false }" class="relative inline-block text-left">
    <div>
        <button @click="open = !open" type="button" class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200 dark:hover:bg-slate-600">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
            </svg>
            Theme
        </button>
    </div>

    <div x-show="open" @click.away="open = false" x-cloak class="origin-top-right absolute right-0 mt-2 w-56 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 focus:outline-none z-50 dark:bg-slate-800 dark:ring-slate-700 dark:divide-slate-700">
        <div class="py-1">
            @foreach($themes as $key => $theme)
                <a href="{{ route('theme.set', $key) }}"
                   class="group flex items-center px-4 py-2 text-sm {{ session('theme', 'standard') === $key ? 'bg-gray-100 text-gray-900 dark:bg-slate-700 dark:text-white' : 'text-gray-700 dark:text-gray-300' }} hover:bg-gray-100 hover:text-gray-900 dark:hover:bg-slate-700 dark:hover:text-white">
                    <div class="mr-3 w-4 h-4 rounded-full {{ $theme['type'] === 'dark' ? 'bg-gray-800 border-2 border-gray-400' : 'bg-white border-2 border-' . $theme['colors']['primary'] . '-500' }}"></div>
                    <span>{{ $theme['name'] }}</span>
                    @if($theme['type'] === 'dark')
                        <svg class="ml-auto w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path>
                        </svg>
                    @else
                        <svg class="ml-auto w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd"></path>
                        </svg>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</div>
