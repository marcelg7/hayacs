@extends('docs.layout')

@section('docs-content')
<h1>Monitoring Overview</h1>

<p class="lead">
    Hay ACS includes comprehensive monitoring using Grafana dashboards backed by Prometheus metrics and MySQL data.
</p>

<div class="info-box">
    <strong>Access Grafana:</strong> <a href="https://hayacs.hay.net/grafana/" target="_blank">https://hayacs.hay.net/grafana/</a>
    <br>
    <span class="text-sm">Contact an administrator if you need a Grafana account.</span>
</div>

<h2>Monitoring Stack</h2>

<p>The monitoring infrastructure consists of:</p>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Component</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Purpose</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Port</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Grafana</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Dashboard visualization & alerting</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">3000 (via /grafana/ proxy)</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Prometheus</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Time-series metrics database</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">9090</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Node Exporter</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Server hardware metrics</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">9100</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">MySQL Exporter</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Database performance metrics</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">9104</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">Apache Exporter</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Web server metrics</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">9117</td>
            </tr>
            <tr>
                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">PHP-FPM Exporter</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">PHP process metrics</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">9253</td>
            </tr>
        </tbody>
    </table>
</div>

<h2>Data Sources</h2>

<p>Grafana connects to two data sources:</p>

<ul>
    <li><strong>Prometheus</strong> - For server infrastructure metrics (CPU, memory, disk, network, database, web server)</li>
    <li><strong>MySQL (Hay ACS)</strong> - For device fleet data (device counts, task status, subscriber info)</li>
</ul>

<h2>Quick Links</h2>

<div class="not-prose space-y-4 my-6">
    <a href="{{ route('docs.show', ['section' => 'monitoring', 'page' => 'dashboards']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Grafana Dashboards</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Available dashboards and what they show.</p>
    </a>
    <a href="{{ route('docs.show', ['section' => 'monitoring', 'page' => 'prometheus']) }}" class="block bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors">
        <h4 class="font-semibold text-gray-900 dark:text-white">Prometheus & Exporters</h4>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Technical details about the monitoring services.</p>
    </a>
</div>

<h2>Service Management</h2>

<p>All monitoring services are managed via systemd and start automatically on boot:</p>

<pre><code># Check status of all monitoring services
sudo systemctl status grafana-server prometheus node_exporter mysqld_exporter apache_exporter phpfpm_exporter

# Restart a service if needed
sudo systemctl restart grafana-server</code></pre>
@endsection
