@props([
    'mac' => '',
    'showCopy' => true,
    'class' => '',
    'monospace' => true,
])

@php
    $displayMac = strtoupper($mac);
    // Normalize MAC to colon format if it has dashes or no separators
    if (strlen(preg_replace('/[^A-Fa-f0-9]/', '', $mac)) === 12) {
        $normalized = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac));
        $displayMac = implode(':', str_split($normalized, 2));
    }
@endphp

<span class="inline-flex items-center {{ $class }}"
      x-data="macOuiLookup('{{ $displayMac }}')"
      @mouseenter="fetchOui()">
    {{-- MAC Address Display --}}
    <span class="{{ $monospace ? 'font-mono' : '' }} text-gray-900 dark:text-gray-100">{{ $displayMac }}</span>

    {{-- Info Icon with Tooltip --}}
    <span class="relative ml-1"
          x-ref="trigger"
          @mouseenter="openTooltip()"
          @mouseleave="startCloseTimer()">
        <button type="button"
                class="text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 focus:outline-none transition-colors"
                @focus="openTooltip()"
                @blur="startCloseTimer()">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </button>

        {{-- Tooltip - positioned dynamically above or below based on available space --}}
        <div x-show="showTooltip"
             x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             :class="positionAbove ? 'bottom-full mb-2' : 'top-full mt-2'"
             class="absolute z-[100] left-1/2 transform -translate-x-1/2 w-72 p-3 text-sm bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700"
             @mouseenter="cancelCloseTimer()"
             @mouseleave="startCloseTimer()">

            {{-- Arrow - points down when above, points up when below --}}
            <div x-show="positionAbove" class="absolute left-1/2 transform -translate-x-1/2 -bottom-2 w-0 h-0 border-l-8 border-r-8 border-t-8 border-l-transparent border-r-transparent border-t-white dark:border-t-gray-800"></div>
            <div x-show="!positionAbove" class="absolute left-1/2 transform -translate-x-1/2 -top-2 w-0 h-0 border-l-8 border-r-8 border-b-8 border-l-transparent border-r-transparent border-b-white dark:border-b-gray-800"></div>

            <template x-if="loading">
                <div class="flex items-center justify-center py-2">
                    <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="ml-2 text-gray-500 dark:text-gray-400">Looking up...</span>
                </div>
            </template>

            <template x-if="!loading && ouiData">
                <div>
                    {{-- Header with Copy All button --}}
                    <div class="flex items-start justify-between mb-2">
                        <div class="font-semibold text-gray-900 dark:text-white" x-text="ouiData.vendor || 'Unknown Vendor'"></div>
                        <button @click.stop="copyAll()"
                                class="ml-2 p-1.5 text-gray-400 hover:text-blue-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors flex-shrink-0"
                                :title="copiedAll ? 'Copied!' : 'Copy all info'">
                            <svg x-show="!copiedAll" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <svg x-show="copiedAll" x-cloak class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </button>
                    </div>

                    {{-- OUI Info --}}
                    <div class="space-y-1 text-xs text-gray-600 dark:text-gray-400 mb-3">
                        <div class="flex">
                            <span class="font-medium w-20">OUI Prefix:</span>
                            <span class="font-mono" x-text="ouiData.prefix || mac.substring(0, 8)"></span>
                        </div>
                        <div class="flex">
                            <span class="font-medium w-20">Full MAC:</span>
                            <span class="font-mono" x-text="mac"></span>
                        </div>
                    </div>

                    {{-- Copy MAC only button --}}
                    <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-900 rounded px-2 py-1.5">
                        <span class="text-xs text-gray-500 dark:text-gray-400">Copy MAC only</span>
                        <button @click.stop="copyMac()"
                                class="p-1 text-gray-400 hover:text-blue-500 rounded transition-colors"
                                :title="copied ? 'Copied!' : 'Copy MAC address'">
                            <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            <svg x-show="copied" x-cloak class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </template>

            <template x-if="!loading && !ouiData">
                <div class="text-gray-500 dark:text-gray-400">
                    <div class="flex items-start justify-between mb-2">
                        <div class="font-medium text-gray-900 dark:text-white">Unknown Manufacturer</div>
                        <button @click.stop="copyMac()"
                                class="ml-2 p-1.5 text-gray-400 hover:text-blue-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded transition-colors flex-shrink-0"
                                :title="copied ? 'Copied!' : 'Copy MAC address'">
                            <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            <svg x-show="copied" x-cloak class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="text-xs mb-2">No OUI data found for this MAC address.</div>
                    <div class="bg-gray-50 dark:bg-gray-900 rounded px-2 py-1.5">
                        <span class="font-mono text-xs" x-text="mac"></span>
                    </div>
                </div>
            </template>
        </div>
    </span>

    @if($showCopy)
    {{-- Standalone Copy Button (outside tooltip) --}}
    <button @click="copyMac()"
            class="ml-1 p-0.5 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            :title="copied ? 'Copied!' : 'Copy MAC address'">
        <svg x-show="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
        </svg>
        <svg x-show="copied" x-cloak class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
    </button>
    @endif
</span>

@once
@push('scripts')
<script>
function macOuiLookup(mac) {
    return {
        mac: mac,
        showTooltip: false,
        loading: false,
        ouiData: null,
        fetched: false,
        copied: false,
        copiedAll: false,
        closeTimer: null,
        positionAbove: false,

        openTooltip() {
            this.cancelCloseTimer();
            this.calculatePosition();
            this.showTooltip = true;
        },

        calculatePosition() {
            // Get the trigger element's position
            const trigger = this.$refs.trigger;
            if (!trigger) return;

            const rect = trigger.getBoundingClientRect();
            const tooltipHeight = 180; // Approximate tooltip height
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;

            // Position above if there's not enough space below but enough above
            this.positionAbove = spaceBelow < tooltipHeight && spaceAbove > tooltipHeight;
        },

        startCloseTimer() {
            this.cancelCloseTimer();
            this.closeTimer = setTimeout(() => {
                this.showTooltip = false;
            }, 300); // 300ms delay before closing
        },

        cancelCloseTimer() {
            if (this.closeTimer) {
                clearTimeout(this.closeTimer);
                this.closeTimer = null;
            }
        },

        async fetchOui() {
            if (this.fetched || this.loading) return;

            this.loading = true;

            try {
                const response = await fetch(`/oui/lookup?mac=${encodeURIComponent(this.mac)}`, {
                    credentials: 'include',
                    headers: { 'X-Background-Poll': 'true' } // Skip loading overlay
                });
                const data = await response.json();

                if (data.found) {
                    this.ouiData = data;
                } else {
                    this.ouiData = null;
                }
            } catch (error) {
                console.error('OUI lookup failed:', error);
                this.ouiData = null;
            }

            this.loading = false;
            this.fetched = true;
        },

        copyMac() {
            navigator.clipboard.writeText(this.mac);
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        },

        copyAll() {
            const vendor = this.ouiData?.vendor || 'Unknown';
            const prefix = this.ouiData?.prefix || this.mac.substring(0, 8);
            const text = `MAC: ${this.mac}\nVendor: ${vendor}\nOUI Prefix: ${prefix}`;
            navigator.clipboard.writeText(text);
            this.copiedAll = true;
            setTimeout(() => this.copiedAll = false, 2000);
        }
    };
}
</script>
@endpush
@endonce
