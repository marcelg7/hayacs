@extends('layouts.app')

@section('title', 'Analytics - TR-069 ACS')

@section('content')
@php
    $theme = session('theme', 'standard');
    $themeConfig = config("themes.{$theme}");
    $colors = $themeConfig['colors'];
@endphp

<div x-data="analyticsData()" x-init="init()" class="space-y-6">
    <!-- Header -->
    <div class="md:flex md:items-center md:justify-between">
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold leading-7 text-gray-900 dark:text-{{ $colors['text'] }} sm:text-3xl">
                Analytics & Historical Trends
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }}">
                Monitor device health, task performance, parameter trends, and fleet-wide statistics
            </p>
        </div>
        <div class="mt-4 flex md:mt-0 md:ml-4">
            <!-- Time Range Selector -->
            <select x-model="timeRange" @change="refreshAll()"
                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-{{ $colors['border'] }} rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} bg-white dark:bg-{{ $colors['card'] }} hover:bg-gray-50">
                <option value="24h">Last 24 Hours</option>
                <option value="7d">Last 7 Days</option>
                <option value="30d">Last 30 Days</option>
                <option value="90d">Last 90 Days</option>
                <option value="1y">Last Year</option>
            </select>
        </div>
    </div>

    <!-- Fleet Analytics -->
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-{{ $colors['text'] }}">Fleet Overview</h3>
            <button @click="loadFleetAnalytics()" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Refresh</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" x-show="fleetData">
            <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} rounded-lg p-4">
                <p class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Total Devices</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-{{ $colors['text'] }}" x-text="fleetData?.total_devices || 0"></p>
            </div>
            <div class="bg-green-50 dark:bg-green-900 rounded-lg p-4">
                <p class="text-sm font-medium text-green-600 dark:text-green-300">Online Devices</p>
                <p class="mt-1 text-3xl font-semibold text-green-900 dark:text-green-100" x-text="fleetData?.online_devices || 0"></p>
                <p class="text-xs text-green-600 dark:text-green-300" x-text="(fleetData?.online_percentage || 0) + '%'"></p>
            </div>
            <div class="bg-red-50 dark:bg-red-900 rounded-lg p-4">
                <p class="text-sm font-medium text-red-600 dark:text-red-300">Offline Devices</p>
                <p class="mt-1 text-3xl font-semibold text-red-900 dark:text-red-100" x-text="fleetData?.offline_devices || 0"></p>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4">
                <p class="text-sm font-medium text-blue-600 dark:text-blue-300">Firmware Versions</p>
                <p class="mt-1 text-3xl font-semibold text-blue-900 dark:text-blue-100" x-text="fleetData?.firmware_distribution?.length || 0"></p>
            </div>
        </div>

        <!-- Distribution Charts -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
            <div>
                <h4 class="text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-3">Firmware Distribution</h4>
                <canvas id="firmwareChart" height="200"></canvas>
            </div>
            <div>
                <h4 class="text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-3">Device Types</h4>
                <canvas id="deviceTypeChart" height="200"></canvas>
            </div>
            <div>
                <h4 class="text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-3">Manufacturers</h4>
                <canvas id="manufacturerChart" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Task Performance -->
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-{{ $colors['text'] }}">Task Performance</h3>
            <button @click="loadTaskPerformance()" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Refresh</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" x-show="taskData">
            <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} rounded-lg p-4">
                <p class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Total Tasks</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-{{ $colors['text'] }}" x-text="taskData?.total_tasks || 0"></p>
            </div>
            <div class="bg-green-50 dark:bg-green-900 rounded-lg p-4">
                <p class="text-sm font-medium text-green-600 dark:text-green-300">Successful</p>
                <p class="mt-1 text-3xl font-semibold text-green-900 dark:text-green-100" x-text="taskData?.successful_tasks || 0"></p>
            </div>
            <div class="bg-red-50 dark:bg-red-900 rounded-lg p-4">
                <p class="text-sm font-medium text-red-600 dark:text-red-300">Failed</p>
                <p class="mt-1 text-3xl font-semibold text-red-900 dark:text-red-100" x-text="taskData?.failed_tasks || 0"></p>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4">
                <p class="text-sm font-medium text-blue-600 dark:text-blue-300">Success Rate</p>
                <p class="mt-1 text-3xl font-semibold text-blue-900 dark:text-blue-100" x-text="(taskData?.success_rate || 0) + '%'"></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div>
                <h4 class="text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-3">Task Timeline</h4>
                <canvas id="taskTimelineChart" height="150"></canvas>
            </div>
            <div>
                <h4 class="text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-3">Task Type Breakdown</h4>
                <div class="max-h-64 overflow-y-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                        <thead class="bg-gray-50 dark:bg-{{ $colors['bg'] }}">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Type</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Total</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Success</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-{{ $colors['border'] }}">
                            <template x-for="type in taskData?.task_type_breakdown || []" :key="type.task_type">
                                <tr>
                                    <td class="px-3 py-2 text-sm text-gray-900 dark:text-{{ $colors['text'] }}" x-text="type.task_type.replace(/_/g, ' ')"></td>
                                    <td class="px-3 py-2 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }} text-right" x-text="type.total"></td>
                                    <td class="px-3 py-2 text-sm text-gray-500 dark:text-{{ $colors['text-muted'] }} text-right" x-text="type.successful"></td>
                                    <td class="px-3 py-2 text-sm text-right">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                              :class="type.success_rate >= 80 ? 'bg-green-100 text-green-800' : type.success_rate >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'"
                                              x-text="type.success_rate + '%'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Common Errors -->
        <div class="mt-6" x-show="taskData?.common_errors?.length > 0">
            <h4 class="text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-3">Most Common Errors</h4>
            <div class="bg-red-50 dark:bg-red-900 rounded-lg p-4">
                <ul class="space-y-2">
                    <template x-for="error in taskData?.common_errors || []" :key="error.error">
                        <li class="flex justify-between text-sm">
                            <span class="text-red-800 dark:text-red-200" x-text="error.error"></span>
                            <span class="text-red-600 dark:text-red-300 font-medium" x-text="error.count + ' occurrences'"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </div>
    </div>

    <!-- SpeedTest Results -->
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-{{ $colors['text'] }}">TR-143 SpeedTest Results</h3>
            <button @click="loadSpeedTestResults()" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Refresh</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" x-show="speedTestData">
            <div class="bg-gray-50 dark:bg-{{ $colors['bg'] }} rounded-lg p-4">
                <p class="text-sm font-medium text-gray-500 dark:text-{{ $colors['text-muted'] }}">Total Tests</p>
                <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-{{ $colors['text'] }}" x-text="speedTestData?.total_tests || 0"></p>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4">
                <p class="text-sm font-medium text-blue-600 dark:text-blue-300">Avg Download</p>
                <p class="mt-1 text-3xl font-semibold text-blue-900 dark:text-blue-100" x-text="(speedTestData?.avg_download_mbps || 0) + ' Mbps'"></p>
            </div>
            <div class="bg-green-50 dark:bg-green-900 rounded-lg p-4">
                <p class="text-sm font-medium text-green-600 dark:text-green-300">Avg Upload</p>
                <p class="mt-1 text-3xl font-semibold text-green-900 dark:text-green-100" x-text="(speedTestData?.avg_upload_mbps || 0) + ' Mbps'"></p>
            </div>
            <div class="bg-purple-50 dark:bg-purple-900 rounded-lg p-4">
                <p class="text-sm font-medium text-purple-600 dark:text-purple-300">Avg Latency</p>
                <p class="mt-1 text-3xl font-semibold text-purple-900 dark:text-purple-100" x-text="(speedTestData?.avg_latency_ms || 0) + ' ms'"></p>
            </div>
        </div>

        <div>
            <h4 class="text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-3">Speed Trends</h4>
            <canvas id="speedTestChart" height="120"></canvas>
        </div>
    </div>

    <!-- Device Health -->
    <div class="bg-white dark:bg-{{ $colors['card'] }} shadow rounded-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-{{ $colors['text'] }}">Device Health Trends</h3>
            <button @click="loadDeviceHealth()" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Refresh</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6" x-show="healthData">
            <div class="bg-green-50 dark:bg-green-900 rounded-lg p-4">
                <p class="text-sm font-medium text-green-600 dark:text-green-300">Uptime</p>
                <p class="mt-1 text-3xl font-semibold text-green-900 dark:text-green-100" x-text="(healthData?.uptime_percent || 0) + '%'"></p>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900 rounded-lg p-4">
                <p class="text-sm font-medium text-blue-600 dark:text-blue-300">Online Snapshots</p>
                <p class="mt-1 text-3xl font-semibold text-blue-900 dark:text-blue-100" x-text="healthData?.online_snapshots || 0"></p>
            </div>
            <div class="bg-red-50 dark:bg-red-900 rounded-lg p-4">
                <p class="text-sm font-medium text-red-600 dark:text-red-300">Offline Snapshots</p>
                <p class="mt-1 text-3xl font-semibold text-red-900 dark:text-red-100" x-text="healthData?.offline_snapshots || 0"></p>
            </div>
        </div>

        <div>
            <h4 class="text-sm font-medium text-gray-700 dark:text-{{ $colors['text'] }} mb-3">Online/Offline Timeline</h4>
            <canvas id="healthChart" height="120"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function analyticsData() {
    return {
        timeRange: '7d',
        fleetData: null,
        taskData: null,
        speedTestData: null,
        healthData: null,

        // Chart instances
        firmwareChart: null,
        deviceTypeChart: null,
        manufacturerChart: null,
        taskTimelineChart: null,
        speedTestChart: null,
        healthChart: null,

        init() {
            this.refreshAll();
        },

        async refreshAll() {
            await Promise.all([
                this.loadFleetAnalytics(),
                this.loadTaskPerformance(),
                this.loadSpeedTestResults(),
                this.loadDeviceHealth()
            ]);
        },

        async loadFleetAnalytics() {
            try {
                const response = await fetch(`/api/analytics/fleet?range=${this.timeRange}`);
                this.fleetData = await response.json();

                // Render charts
                this.$nextTick(() => {
                    this.renderFirmwareChart();
                    this.renderDeviceTypeChart();
                    this.renderManufacturerChart();
                });
            } catch (error) {
                console.error('Error loading fleet analytics:', error);
            }
        },

        async loadTaskPerformance() {
            try {
                const response = await fetch(`/api/analytics/task-performance?range=${this.timeRange}`);
                this.taskData = await response.json();

                this.$nextTick(() => {
                    this.renderTaskTimelineChart();
                });
            } catch (error) {
                console.error('Error loading task performance:', error);
            }
        },

        async loadSpeedTestResults() {
            try {
                const response = await fetch(`/api/analytics/speedtest-results?range=${this.timeRange}`);
                this.speedTestData = await response.json();

                this.$nextTick(() => {
                    this.renderSpeedTestChart();
                });
            } catch (error) {
                console.error('Error loading speedtest results:', error);
            }
        },

        async loadDeviceHealth() {
            try {
                const response = await fetch(`/api/analytics/device-health?range=${this.timeRange}`);
                this.healthData = await response.json();

                this.$nextTick(() => {
                    this.renderHealthChart();
                });
            } catch (error) {
                console.error('Error loading device health:', error);
            }
        },

        renderFirmwareChart() {
            const ctx = document.getElementById('firmwareChart');
            if (!ctx || !this.fleetData?.firmware_distribution) return;

            if (this.firmwareChart) this.firmwareChart.destroy();

            this.firmwareChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: this.fleetData.firmware_distribution.map(f => f.version || 'Unknown'),
                    datasets: [{
                        data: this.fleetData.firmware_distribution.map(f => f.count),
                        backgroundColor: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        },

        renderDeviceTypeChart() {
            const ctx = document.getElementById('deviceTypeChart');
            if (!ctx || !this.fleetData?.device_type_distribution) return;

            if (this.deviceTypeChart) this.deviceTypeChart.destroy();

            this.deviceTypeChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: this.fleetData.device_type_distribution.map(d => d.product_class || 'Unknown'),
                    datasets: [{
                        data: this.fleetData.device_type_distribution.map(d => d.count),
                        backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        },

        renderManufacturerChart() {
            const ctx = document.getElementById('manufacturerChart');
            if (!ctx || !this.fleetData?.manufacturer_distribution) return;

            if (this.manufacturerChart) this.manufacturerChart.destroy();

            this.manufacturerChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: this.fleetData.manufacturer_distribution.map(m => m.manufacturer || 'Unknown'),
                    datasets: [{
                        data: this.fleetData.manufacturer_distribution.map(m => m.count),
                        backgroundColor: ['#F59E0B', '#10B981', '#3B82F6', '#EF4444', '#8B5CF6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        },

        renderTaskTimelineChart() {
            const ctx = document.getElementById('taskTimelineChart');
            if (!ctx || !this.taskData?.chart_data) return;

            if (this.taskTimelineChart) this.taskTimelineChart.destroy();

            this.taskTimelineChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: this.taskData.chart_data.map(d => new Date(d.timestamp).toLocaleString()),
                    datasets: [
                        {
                            label: 'Total',
                            data: this.taskData.chart_data.map(d => d.total),
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Successful',
                            data: this.taskData.chart_data.map(d => d.successful),
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4
                        },
                        {
                            label: 'Failed',
                            data: this.taskData.chart_data.map(d => d.failed),
                            borderColor: '#EF4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        },

        renderSpeedTestChart() {
            const ctx = document.getElementById('speedTestChart');
            if (!ctx || !this.speedTestData?.chart_data) return;

            if (this.speedTestChart) this.speedTestChart.destroy();

            this.speedTestChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: this.speedTestData.chart_data.map(d => new Date(d.timestamp).toLocaleString()),
                    datasets: [
                        {
                            label: 'Download (Mbps)',
                            data: this.speedTestData.chart_data.map(d => d.download_mbps),
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Upload (Mbps)',
                            data: this.speedTestData.chart_data.map(d => d.upload_mbps),
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Latency (ms)',
                            data: this.speedTestData.chart_data.map(d => d.latency_ms),
                            borderColor: '#8B5CF6',
                            backgroundColor: 'rgba(139, 92, 246, 0.1)',
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: { display: true, text: 'Speed (Mbps)' }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: { display: true, text: 'Latency (ms)' },
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });
        },

        renderHealthChart() {
            const ctx = document.getElementById('healthChart');
            if (!ctx || !this.healthData?.chart_data) return;

            if (this.healthChart) this.healthChart.destroy();

            this.healthChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: this.healthData.chart_data.map(d => new Date(d.timestamp).toLocaleString()),
                    datasets: [
                        {
                            label: 'Online',
                            data: this.healthData.chart_data.map(d => d.online_count),
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.2)',
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Offline',
                            data: this.healthData.chart_data.map(d => d.offline_count),
                            borderColor: '#EF4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.2)',
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'bottom' } },
                    scales: {
                        y: { beginAtZero: true, stacked: false }
                    }
                }
            });
        }
    };
}
</script>
@endsection
