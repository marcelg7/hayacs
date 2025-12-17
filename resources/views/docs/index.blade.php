@extends('docs.layout')

@section('docs-content')
<h1>Hay ACS Documentation</h1>

<p class="lead">
    Welcome to the Hay ACS documentation. This guide covers everything you need to know to effectively use the Auto Configuration Server for managing your network devices.
</p>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-8 not-prose">
    @foreach($structure as $sectionKey => $section)
    <a href="{{ route('docs.show', ['section' => $sectionKey, 'page' => 'index']) }}"
       class="block p-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 hover:shadow-md transition-all">
        <div class="flex items-center mb-3">
            <div class="p-2 bg-indigo-100 dark:bg-indigo-900/50 rounded-lg text-indigo-600 dark:text-indigo-400">
                @include('docs.partials.icon', ['icon' => $section['icon']])
            </div>
            <h3 class="ml-3 text-lg font-semibold text-gray-900 dark:text-white">
                {{ $section['title'] }}
                @if(isset($section['admin_only']) && $section['admin_only'])
                <span class="ml-2 px-2 py-0.5 text-xs bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 rounded">Admin</span>
                @endif
            </h3>
        </div>
        <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
            @foreach(array_slice($section['pages'], 0, 4) as $pageKey => $pageTitle)
            <li>{{ $pageTitle }}</li>
            @endforeach
            @if(count($section['pages']) > 4)
            <li class="text-indigo-600 dark:text-indigo-400">+ {{ count($section['pages']) - 4 }} more...</li>
            @endif
        </ul>
    </a>
    @endforeach
</div>

<h2 class="mt-12">Quick Start</h2>

<p>New to Hay ACS? Here's where to begin:</p>

<ol>
    <li><a href="{{ route('docs.show', ['section' => 'getting-started', 'page' => 'first-login']) }}">First Login & Password Setup</a> - Set up your account</li>
    <li><a href="{{ route('docs.show', ['section' => 'getting-started', 'page' => 'two-factor-auth']) }}">Two-Factor Authentication</a> - Secure your account</li>
    <li><a href="{{ route('docs.show', ['section' => 'getting-started', 'page' => 'trusted-device']) }}">Trust This Device</a> - Simplify field access</li>
    <li><a href="{{ route('docs.show', ['section' => 'devices', 'page' => 'device-dashboard']) }}">Device Dashboard</a> - Learn the main interface</li>
</ol>

<h2>About Hay ACS</h2>

<p>
    Hay ACS (Auto Configuration Server) is a TR-069/CWMP management platform for remotely configuring and monitoring customer premises equipment (CPE). It supports devices from multiple manufacturers including:
</p>

<ul>
    <li><strong>Calix</strong> - GigaCenters (844E, 844G, 854G) and GigaSpires (GS4220E)</li>
    <li><strong>Nokia/Alcatel-Lucent</strong> - Beacon 6, Beacon 2, Beacon 3.1</li>
    <li><strong>SmartRG/Sagemcom</strong> - SR505N, SR515ac, SR516ac</li>
</ul>

<h2>Need Help?</h2>

<p>
    If you encounter issues or have questions not covered in this documentation, contact your system administrator.
</p>
@endsection
