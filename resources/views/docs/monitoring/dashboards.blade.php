@extends('docs.layout')

@section('docs-content')
<h1>Grafana Dashboards</h1>

<p class="lead">
    Hay ACS includes 8 pre-configured dashboards for monitoring the device fleet and server infrastructure.
</p>

<div class="info-box">
    <strong>Access:</strong> <a href="https://hayacs.hay.net/grafana/" target="_blank">https://hayacs.hay.net/grafana/</a>
</div>

<h2>Fleet Monitoring Dashboards</h2>

<p>These dashboards query the Hay ACS MySQL database for device and task information.</p>

<h3>Executive Summary</h3>
<p>A single-pane-of-glass overview showing key metrics at a glance:</p>
<ul>
    <li>Total devices and online count</li>
    <li>Online percentage</li>
    <li>Pending tasks</li>
    <li>Server CPU, memory, and disk usage</li>
</ul>

<h3>Task Performance</h3>
<p>Monitors the TR-069 task queue health:</p>
<ul>
    <li><strong>Gateway Success Rate</strong> - Task completion rate for root devices (gateways)</li>
    <li><strong>Mesh AP Success Rate</strong> - Task completion rate for mesh access points (typically lower due to NAT)</li>
    <li><strong>Tasks by Status</strong> - Distribution of pending, completed, failed tasks</li>
    <li><strong>Failed Tasks by Type</strong> - Which task types are failing most</li>
    <li><strong>Initial Backup Progress</strong> - Percentage of devices with initial backup complete</li>
</ul>

<div class="tip-box">
    <strong>Note:</strong> Mesh APs (804Mesh, Beacon 2/3, GigaMesh) have lower success rates because they're behind NAT and can only receive commands during periodic informs.
</div>

<h3>Device Health</h3>
<p>Fleet-wide device status:</p>
<ul>
    <li><strong>Device Status</strong> - Online vs offline pie chart</li>
    <li><strong>Offline > 24 Hours</strong> - Devices that haven't connected recently (potential issues)</li>
    <li><strong>Top Firmware Versions</strong> - Distribution of firmware across the fleet</li>
    <li><strong>New Devices (7 Days)</strong> - Recently added devices</li>
    <li><strong>Unlinked Devices</strong> - Devices without subscriber association</li>
</ul>

<h3>Subscriber</h3>
<p>Billing and subscriber metrics:</p>
<ul>
    <li>Subscribers with linked devices</li>
    <li>Subscribers without devices</li>
    <li>Top subscribers by device count</li>
</ul>

<h2>Infrastructure Dashboards</h2>

<p>These dashboards use Prometheus metrics to monitor server health.</p>

<h3>Node Exporter Full</h3>
<p>Comprehensive server metrics:</p>
<ul>
    <li>CPU usage by core and mode</li>
    <li>Memory usage and swap</li>
    <li>Disk I/O and space</li>
    <li>Network traffic and errors</li>
    <li>System load and uptime</li>
</ul>

<h3>MySQL Overview</h3>
<p>Database performance metrics:</p>
<ul>
    <li>Queries per second</li>
    <li>Active connections</li>
    <li>InnoDB buffer pool usage</li>
    <li>Slow queries</li>
    <li>Table locks and deadlocks</li>
</ul>

<h3>Apache</h3>
<p>Web server metrics:</p>
<ul>
    <li>Requests per second</li>
    <li>Active workers</li>
    <li>Bytes served</li>
    <li>Connection states</li>
</ul>

<h3>PHP-FPM</h3>
<p>PHP process metrics:</p>
<ul>
    <li>Active and idle processes</li>
    <li>Accepted connections</li>
    <li>Listen queue length</li>
    <li>Max children reached</li>
</ul>

<h2>Dashboard Summary</h2>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Dashboard</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data Source</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Primary Use</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Executive Summary</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">MySQL + Prometheus</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Quick overview for meetings</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Task Performance</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">MySQL</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">TR-069 task queue health</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Device Health</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">MySQL</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Fleet status monitoring</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Subscriber</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">MySQL</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Billing/subscriber metrics</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Node Exporter Full</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Prometheus</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Server hardware metrics</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">MySQL Overview</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Prometheus</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Database performance</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Apache</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Prometheus</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Web server metrics</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">PHP-FPM</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Prometheus</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">PHP process metrics</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Useful SQL Queries</h2>

<p>These queries can be used to create custom panels in Grafana using the MySQL data source:</p>

<h3>Device Counts</h3>
<pre><code>-- Total devices
SELECT COUNT(*) as total FROM devices

-- Online devices
SELECT COUNT(*) as online FROM devices WHERE online = 1

-- Online percentage
SELECT ROUND(SUM(online) * 100.0 / COUNT(*), 1) as pct FROM devices

-- Devices by manufacturer
SELECT manufacturer, COUNT(*) as count FROM devices GROUP BY manufacturer ORDER BY count DESC</code></pre>

<h3>Task Metrics</h3>
<pre><code>-- Gateway success rate (last 24h)
SELECT ROUND(SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as success_rate
FROM tasks t
JOIN devices d ON t.device_id = d.id
WHERE t.created_at > NOW() - INTERVAL 24 HOUR
AND d.product_class NOT LIKE '%Mesh%'
AND d.product_class NOT LIKE '%Beacon 2%'
AND d.product_class NOT LIKE '%Beacon 3%'

-- Pending tasks
SELECT COUNT(*) as pending FROM tasks WHERE status = 'pending'

-- Failed tasks by type
SELECT task_type, COUNT(*) as failures
FROM tasks WHERE status = 'failed' AND created_at > NOW() - INTERVAL 24 HOUR
GROUP BY task_type ORDER BY failures DESC</code></pre>
@endsection
