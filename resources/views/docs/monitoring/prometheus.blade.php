@extends('docs.layout')

@section('docs-content')
<h1>Prometheus & Exporters</h1>

<p class="lead">
    Technical documentation for the Prometheus monitoring stack and its exporters.
</p>

<h2>Architecture</h2>

<p>Prometheus scrapes metrics from various exporters every 15 seconds. Grafana queries Prometheus to display time-series data.</p>

<pre><code>┌─────────────────────────────────────────────────────────────┐
│  Grafana (localhost:3000 → /grafana/)                       │
│  └── Queries Prometheus and MySQL                           │
└─────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
┌─────────────────────────┐     ┌─────────────────────────┐
│  Prometheus (:9090)     │     │  MySQL (Hay ACS DB)     │
│  └── Scrapes exporters  │     │  └── Device/task data   │
└─────────────────────────┘     └─────────────────────────┘
              │
    ┌─────────┼─────────┬─────────┬─────────┐
    ▼         ▼         ▼         ▼         ▼
 Node      MySQL     Apache   PHP-FPM   (future)
 :9100     :9104     :9117    :9253</code></pre>

<h2>Prometheus Configuration</h2>

<p>Configuration file: <code>/etc/prometheus/prometheus.yml</code></p>

<pre><code>global:
  scrape_interval: 15s

scrape_configs:
  - job_name: "prometheus"
    static_configs:
      - targets: ["localhost:9090"]

  - job_name: "node"
    static_configs:
      - targets: ["localhost:9100"]

  - job_name: "mysql"
    static_configs:
      - targets: ["localhost:9104"]

  - job_name: "apache"
    static_configs:
      - targets: ["localhost:9117"]

  - job_name: "phpfpm"
    static_configs:
      - targets: ["localhost:9253"]</code></pre>

<h2>Exporters</h2>

<h3>Node Exporter (Port 9100)</h3>

<p>Collects hardware and OS metrics from the server.</p>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Item</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Value</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Binary</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><code>/usr/local/bin/node_exporter</code></td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Service</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><code>node_exporter.service</code></td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Metrics</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">CPU, memory, disk, network, filesystem</td>
            </tr>
        </tbody>
    </table>
</div>

<h3>MySQL Exporter (Port 9104)</h3>

<p>Collects database performance metrics.</p>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Item</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Value</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Binary</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><code>/usr/local/bin/mysqld_exporter</code></td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Service</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><code>mysqld_exporter.service</code></td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Config</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><code>/etc/prometheus/.mysqld_exporter.cnf</code></td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Metrics</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Queries, connections, InnoDB, replication</td>
            </tr>
        </tbody>
    </table>
</div>

<h3>Apache Exporter (Port 9117)</h3>

<p>Collects web server metrics from Apache's mod_status.</p>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Item</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Value</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Binary</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><code>/usr/local/bin/apache_exporter</code></td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Service</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><code>apache_exporter.service</code></td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Status URL</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><code>http://localhost/server-status?auto</code></td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Metrics</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Requests, workers, bytes, scoreboard</td>
            </tr>
        </tbody>
    </table>
</div>

<h3>PHP-FPM Exporter (Port 9253)</h3>

<p>Collects PHP process pool metrics.</p>

<div class="not-prose overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Item</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Value</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Binary</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><code>/usr/local/bin/php-fpm_exporter</code></td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Service</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><code>phpfpm_exporter.service</code></td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Socket</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><code>unix:///run/php-fpm/www.sock</code></td>
            </tr>
            <tr>
                <td class="px-4 py-3 text-gray-900 dark:text-white">Metrics</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">Active/idle processes, queue, connections</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="warning-box">
    <strong>Note:</strong> The PHP-FPM exporter requires ACL permission on the socket. This is configured via a systemd override in <code>/etc/systemd/system/php-fpm.service.d/prometheus.conf</code>.
</div>

<h2>Common Commands</h2>

<h3>Check Service Status</h3>
<pre><code># All monitoring services
sudo systemctl status prometheus node_exporter mysqld_exporter apache_exporter phpfpm_exporter

# Grafana
sudo systemctl status grafana-server</code></pre>

<h3>View Logs</h3>
<pre><code># Prometheus logs
sudo journalctl -u prometheus -f

# Grafana logs
sudo journalctl -u grafana-server -f</code></pre>

<h3>Verify Targets</h3>
<pre><code># Check all Prometheus targets are healthy
curl -s http://localhost:9090/api/v1/targets | grep -o '"health":"[^"]*"' | sort | uniq -c

# Should show: 5 "health":"up"</code></pre>

<h3>Test Individual Exporters</h3>
<pre><code># Node exporter
curl -s http://localhost:9100/metrics | head -5

# MySQL exporter
curl -s http://localhost:9104/metrics | grep mysql_up

# Apache exporter
curl -s http://localhost:9117/metrics | grep apache_up

# PHP-FPM exporter
curl -s http://localhost:9253/metrics | grep phpfpm_up</code></pre>

<h2>Troubleshooting</h2>

<h3>Exporter Not Starting</h3>
<ol>
    <li>Check SELinux context: <code>sudo chcon -t bin_t /usr/local/bin/&lt;exporter&gt;</code></li>
    <li>Check file permissions: <code>ls -la /usr/local/bin/&lt;exporter&gt;</code></li>
    <li>View service logs: <code>sudo journalctl -u &lt;service&gt; -n 50</code></li>
</ol>

<h3>Grafana Shows No Data</h3>
<ol>
    <li>Verify Prometheus targets: <code>curl http://localhost:9090/api/v1/targets</code></li>
    <li>Check data source configuration in Grafana</li>
    <li>Ensure time range in dashboard covers available data</li>
</ol>

<h3>PHP-FPM Exporter Permission Denied</h3>
<pre><code># Add ACL for prometheus user
sudo setfacl -m u:prometheus:rw /run/php-fpm/www.sock

# Make it persist across restarts
sudo mkdir -p /etc/systemd/system/php-fpm.service.d
echo -e "[Service]\nExecStartPost=/usr/bin/setfacl -m u:prometheus:rw /run/php-fpm/www.sock" | \
  sudo tee /etc/systemd/system/php-fpm.service.d/prometheus.conf
sudo systemctl daemon-reload</code></pre>
@endsection
