{{-- Quick Action Button Partial --}}
@php
    $styleClasses = [
        'primary' => "bg-{$colors['btn-primary']}-600 hover:bg-{$colors['btn-primary']}-700 text-white",
        'secondary' => 'border border-gray-300 dark:border-' . $colors['border'] . ' text-gray-700 dark:text-' . $colors['text'] . ' bg-white dark:bg-' . $colors['card'] . ' hover:bg-gray-50 dark:hover:bg-' . $colors['bg'],
        'success' => "bg-{$colors['btn-success']}-600 hover:bg-{$colors['btn-success']}-700 text-white",
        'danger' => "bg-{$colors['btn-danger']}-600 hover:bg-{$colors['btn-danger']}-700 text-white",
        'warning' => "bg-{$colors['btn-warning']}-600 hover:bg-{$colors['btn-warning']}-700 text-white",
        'info' => "bg-{$colors['btn-info']}-600 hover:bg-{$colors['btn-info']}-700 text-white",
    ];
    $buttonClass = $styleClasses[$style] ?? $styleClasses['primary'];
    $isDisabled = ($action === 'traceroute' && $device->manufacturer === 'SmartRG');
@endphp

@if($action === 'query')
<form @submit.prevent="async (e) => {
    taskLoading = true;
    taskMessage = 'Querying Device Info...';
    try {
        const response = await fetch('/api/devices/{{ $device->id }}/query', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        const result = await response.json();
        if (result.task && result.task.id) {
            startTaskTracking('Querying Device Info...', result.task.id);
        } else {
            taskLoading = false;
            alert('Query started, but no task ID returned');
        }
    } catch (error) {
        taskLoading = false;
        alert('Error querying device: ' + error.message);
    }
}">
    @csrf
    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 rounded-md shadow-sm text-sm font-medium {{ $buttonClass }}">
        {{ $label }}
    </button>
</form>
@elseif($action === 'connect')
<form @submit.prevent="async (e) => {
    try {
        const response = await fetch('/api/devices/{{ $device->id }}/connection-request', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        });
        const result = await response.json();
        if (response.ok) {
            alert('Connection request sent successfully!');
        } else {
            alert('Error: ' + (result.error || result.message || 'Failed to send connection request'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}">
    @csrf
    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium {{ $buttonClass }}">
        {{ $label }}
    </button>
</form>
@elseif($action === 'reboot')
<form @submit.prevent="async (e) => {
    if (!confirm('Are you sure you want to reboot this device?')) return;
    taskLoading = true;
    taskMessage = 'Initiating Reboot...';
    try {
        const response = await fetch('/api/devices/{{ $device->id }}/reboot', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        const result = await response.json();
        if (result.task && result.task.id) {
            startTaskTracking('Rebooting Device...', result.task.id);
        } else {
            taskLoading = false;
            alert('Error: ' + (result.error || result.message || 'Unknown error'));
        }
    } catch (error) {
        taskLoading = false;
        alert('Error initiating reboot: ' + error.message);
    }
}">
    @csrf
    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium {{ $buttonClass }}">
        {{ $label }}
    </button>
</form>
@elseif($action === 'ping')
<form @submit.prevent="async (e) => {
    taskLoading = true;
    taskMessage = 'Running Ping Test...';
    try {
        const response = await fetch('/api/devices/{{ $device->id }}/ping-test', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        const result = await response.json();
        if (result.task && result.task.id) {
            startTaskTracking('Running Ping Test to 8.8.8.8...', result.task.id);
        } else {
            taskLoading = false;
            alert('Ping test started, but no task ID returned');
        }
    } catch (error) {
        taskLoading = false;
        alert('Error starting ping test: ' + error.message);
    }
}">
    @csrf
    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium {{ $buttonClass }}">
        {{ $label }}
    </button>
</form>
@elseif($action === 'traceroute')
<form @submit.prevent="async (e) => {
    if ('{{ $device->manufacturer }}' === 'SmartRG') {
        alert('Traceroute is not supported for SmartRG devices');
        return;
    }
    taskLoading = true;
    taskMessage = 'Running Trace Route...';
    try {
        const response = await fetch('/api/devices/{{ $device->id }}/traceroute-test', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        const result = await response.json();
        if (result.task && result.task.id) {
            startTaskTracking('Running Trace Route to 8.8.8.8...', result.task.id);
        } else {
            taskLoading = false;
            alert('Trace route started, but no task ID returned');
        }
    } catch (error) {
        taskLoading = false;
        alert('Error starting trace route: ' + error.message);
    }
}">
    @csrf
    <button type="submit" {{ $isDisabled ? 'disabled' : '' }}
        class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium {{ $buttonClass }} {{ $isDisabled ? 'opacity-50 cursor-not-allowed' : '' }}">
        {{ $label }}
        @if($isDisabled)
            <svg class="w-4 h-4 ml-1 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
            </svg>
        @endif
    </button>
</form>
@elseif($action === 'firmware')
<form @submit.prevent="async (e) => {
    if (!confirm('Are you sure you want to upgrade the firmware?')) return;
    taskLoading = true;
    taskMessage = 'Initiating Firmware Upgrade...';
    try {
        const response = await fetch('/api/devices/{{ $device->id }}/firmware-upgrade', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        const result = await response.json();
        if (result.task && result.task.id) {
            startTaskTracking('Upgrading Firmware...', result.task.id);
        } else {
            taskLoading = false;
            alert('Error: ' + (result.error || result.message || 'Unknown error'));
        }
    } catch (error) {
        taskLoading = false;
        alert('Error initiating firmware upgrade: ' + error.message);
    }
}">
    @csrf
    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium {{ $buttonClass }}">
        {{ $label }}
    </button>
</form>
@elseif($action === 'factory-reset')
<form @submit.prevent="async (e) => {
    if (!confirm('WARNING: This will erase ALL device settings! Are you sure?')) return;
    taskLoading = true;
    taskMessage = 'Initiating Factory Reset...';
    try {
        const response = await fetch('/api/devices/{{ $device->id }}/factory-reset', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        const result = await response.json();
        if (result.task && result.task.id) {
            startTaskTracking('Factory Resetting Device...', result.task.id);
        } else {
            taskLoading = false;
            alert('Error: ' + (result.error || result.message || 'Unknown error'));
        }
    } catch (error) {
        taskLoading = false;
        alert('Error initiating factory reset: ' + error.message);
    }
}">
    @csrf
    <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium {{ $buttonClass }}">
        {{ $label }}
    </button>
</form>
@endif
