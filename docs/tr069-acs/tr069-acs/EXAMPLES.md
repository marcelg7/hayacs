# TR-069 ACS - Real World Examples

This guide provides practical, copy-paste examples for common TR-069 ACS operations.

## Table of Contents
1. [Basic Device Management](#basic-device-management)
2. [WiFi Configuration](#wifi-configuration)
3. [Bulk Operations](#bulk-operations)
4. [Monitoring & Alerts](#monitoring--alerts)
5. [Automated Provisioning](#automated-provisioning)
6. [Diagnostics](#diagnostics)
7. [Integration Examples](#integration-examples)

## Basic Device Management

### Get Device Information

```python
import requests

# Get all devices
response = requests.get("http://localhost:8080/api/devices")
devices = response.json()

for device in devices:
    print(f"Device: {device['id']}")
    print(f"  Manufacturer: {device['manufacturer']}")
    print(f"  Model: {device['product_class']}")
    print(f"  Status: {'Online' if device['online'] else 'Offline'}")
    print(f"  SW Version: {device['software_version']}")
    print()
```

### Get Device Parameters

```python
# Get all parameters for a device
device_id = "ABCDEF-Router-12345"
response = requests.get(f"http://localhost:8080/api/devices/{device_id}/parameters")
params = response.json()

# Filter parameters by prefix
wan_params = [p for p in params if 'WANDevice' in p['name']]
for param in wan_params:
    print(f"{param['name']}: {param['value']}")
```

### Request Specific Parameters

```python
# Create task to get specific parameters
device_id = "ABCDEF-Router-12345"
task = {
    "type": "get_params",
    "parameters": {
        "names": [
            "InternetGatewayDevice.DeviceInfo.SoftwareVersion",
            "InternetGatewayDevice.DeviceInfo.UpTime",
            "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress",
            "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ConnectionStatus"
        ]
    }
}

response = requests.post(
    f"http://localhost:8080/api/devices/{device_id}/tasks",
    json=task
)
print(f"Task created: {response.json()['id']}")
```

## WiFi Configuration

### Change WiFi SSID

```python
device_id = "ABCDEF-Router-12345"
task = {
    "type": "set_params",
    "parameters": {
        "values": {
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID": "MyNewNetwork",
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.BeaconAdvertisementEnabled": "1"
        }
    }
}

response = requests.post(
    f"http://localhost:8080/api/devices/{device_id}/tasks",
    json=task
)
print(f"WiFi SSID change scheduled: {response.json()['id']}")
```

### Change WiFi Password

```python
device_id = "ABCDEF-Router-12345"
task = {
    "type": "set_params",
    "parameters": {
        "values": {
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey": "NewSecurePassword123"
        }
    }
}

response = requests.post(
    f"http://localhost:8080/api/devices/{device_id}/tasks",
    json=task
)
```

### Disable WiFi

```python
task = {
    "type": "set_params",
    "parameters": {
        "values": {
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable": "0"
        }
    }
}

response = requests.post(
    f"http://localhost:8080/api/devices/{device_id}/tasks",
    json=task
)
```

### Change WiFi Channel

```python
task = {
    "type": "set_params",
    "parameters": {
        "values": {
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel": "6"
        }
    }
}

response = requests.post(
    f"http://localhost:8080/api/devices/{device_id}/tasks",
    json=task
)
```

## Bulk Operations

### Reboot All Offline Devices

```python
from datetime import datetime, timedelta

# Get all devices
response = requests.get("http://localhost:8080/api/devices")
devices = response.json()

offline_threshold = datetime.utcnow() - timedelta(hours=2)

for device in devices:
    if device['last_inform']:
        last_seen = datetime.fromisoformat(device['last_inform'])
        if last_seen < offline_threshold:
            print(f"Device {device['id']} offline for >2 hours")
            # Note: Device needs to connect first to receive reboot command
```

### Update Configuration for All Devices

```python
# Get all online devices
response = requests.get("http://localhost:8080/api/devices")
devices = response.json()

# New configuration
new_config = {
    "InternetGatewayDevice.ManagementServer.PeriodicInformInterval": "300",
    "InternetGatewayDevice.ManagementServer.PeriodicInformEnable": "1"
}

for device in devices:
    if device['online']:
        task = {
            "type": "set_params",
            "parameters": {
                "values": new_config
            }
        }
        
        response = requests.post(
            f"http://localhost:8080/api/devices/{device['id']}/tasks",
            json=task
        )
        print(f"Scheduled config update for {device['id']}")
```

### Update WiFi Settings for Specific Models

```python
# Get all devices
response = requests.get("http://localhost:8080/api/devices")
devices = response.json()

# Filter by model
target_model = "HomeRouter5G"
target_devices = [d for d in devices if d['product_class'] == target_model]

# New WiFi settings
wifi_config = {
    "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID": "CorporateWiFi",
    "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel": "11",
    "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Standard": "n"
}

for device in target_devices:
    task = {
        "type": "set_params",
        "parameters": {
            "values": wifi_config
        }
    }
    
    requests.post(
        f"http://localhost:8080/api/devices/{device['id']}/tasks",
        json=task
    )
    print(f"Updated {device['id']}")
```

## Monitoring & Alerts

### Check Device Status

```python
import requests
from datetime import datetime, timedelta

def check_device_health():
    response = requests.get("http://localhost:8080/api/devices")
    devices = response.json()
    
    offline_threshold = datetime.utcnow() - timedelta(minutes=30)
    
    issues = []
    
    for device in devices:
        # Check if device hasn't reported in 30 minutes
        if device['last_inform']:
            last_seen = datetime.fromisoformat(device['last_inform'])
            if last_seen < offline_threshold:
                issues.append({
                    'device_id': device['id'],
                    'issue': 'Not seen for >30 minutes',
                    'last_seen': last_seen
                })
    
    return issues

# Run check
issues = check_device_health()
for issue in issues:
    print(f"⚠️  {issue['device_id']}: {issue['issue']}")
    print(f"   Last seen: {issue['last_seen']}")
```

### Monitor Software Versions

```python
from collections import Counter

response = requests.get("http://localhost:8080/api/devices")
devices = response.json()

# Count devices by software version
versions = Counter(d['software_version'] for d in devices if d['software_version'])

print("Software Version Distribution:")
for version, count in versions.most_common():
    print(f"  {version}: {count} devices")

# Find devices on old versions
old_version = "1.0.0"
old_devices = [d for d in devices if d['software_version'] == old_version]
print(f"\n{len(old_devices)} devices need upgrade from {old_version}")
```

### Connection Quality Report

```python
def get_connection_quality_report():
    response = requests.get("http://localhost:8080/api/devices")
    devices = response.json()
    
    report = {
        'total': len(devices),
        'online': 0,
        'offline': 0,
        'never_connected': 0
    }
    
    for device in devices:
        if not device['last_inform']:
            report['never_connected'] += 1
        elif device['online']:
            report['online'] += 1
        else:
            report['offline'] += 1
    
    print(f"Device Status Report:")
    print(f"  Total Devices: {report['total']}")
    print(f"  🟢 Online: {report['online']} ({report['online']/report['total']*100:.1f}%)")
    print(f"  🔴 Offline: {report['offline']} ({report['offline']/report['total']*100:.1f}%)")
    print(f"  ⚪ Never Connected: {report['never_connected']}")
    
    return report

report = get_connection_quality_report()
```

## Automated Provisioning

### Auto-configure New Devices

```python
# Add this to main.py in the cwmp_endpoint function

def auto_provision_device(device_id, device_info, db):
    """Automatically provision new devices"""
    
    # Check if device is new (first bootstrap)
    # This would be triggered on '0 BOOTSTRAP' event
    
    # Standard configuration for all devices
    standard_config = {
        "InternetGatewayDevice.ManagementServer.PeriodicInformInterval": "300",
        "InternetGatewayDevice.ManagementServer.PeriodicInformEnable": "1",
        "InternetGatewayDevice.Time.Enable": "1",
        "InternetGatewayDevice.Time.NTPServer1": "pool.ntp.org"
    }
    
    # Model-specific configuration
    if device_info.get('product_class') == 'HomeRouter':
        standard_config.update({
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID": f"Home-{device_info['serial_number'][-4:]}",
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel": "6"
        })
    
    # Create provisioning task
    task = Task(
        device_id=device_id,
        task_type='set_params',
        parameters={'values': standard_config},
        status='pending'
    )
    db.add(task)
    db.commit()
    
    return task.id
```

### Location-based Configuration

```python
# Configuration templates by location
LOCATION_CONFIGS = {
    'office': {
        "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID": "OfficeWiFi",
        "InternetGatewayDevice.ManagementServer.PeriodicInformInterval": "180"
    },
    'warehouse': {
        "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID": "WarehouseWiFi",
        "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel": "1"
    },
    'retail': {
        "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID": "GuestWiFi",
        "InternetGatewayDevice.ManagementServer.PeriodicInformInterval": "300"
    }
}

def provision_by_location(device_id, location):
    if location not in LOCATION_CONFIGS:
        raise ValueError(f"Unknown location: {location}")
    
    task = {
        "type": "set_params",
        "parameters": {
            "values": LOCATION_CONFIGS[location]
        }
    }
    
    response = requests.post(
        f"http://localhost:8080/api/devices/{device_id}/tasks",
        json=task
    )
    return response.json()

# Example usage
provision_by_location("ABCDEF-Router-12345", "office")
```

## Diagnostics

### Get Diagnostic Information

```python
def get_device_diagnostics(device_id):
    """Get comprehensive diagnostic information"""
    
    diagnostic_params = [
        # Device Info
        "InternetGatewayDevice.DeviceInfo.UpTime",
        "InternetGatewayDevice.DeviceInfo.SoftwareVersion",
        "InternetGatewayDevice.DeviceInfo.HardwareVersion",
        
        # Memory
        "InternetGatewayDevice.DeviceInfo.MemoryStatus.Total",
        "InternetGatewayDevice.DeviceInfo.MemoryStatus.Free",
        
        # WAN Status
        "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ConnectionStatus",
        "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress",
        "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ConnectionUptime",
        
        # LAN Status
        "InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPInterfaceNumberOfEntries",
        
        # WiFi Status
        "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Status",
        "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel",
        "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.TotalAssociations"
    ]
    
    task = {
        "type": "get_params",
        "parameters": {
            "names": diagnostic_params
        }
    }
    
    response = requests.post(
        f"http://localhost:8080/api/devices/{device_id}/tasks",
        json=task
    )
    return response.json()

# Run diagnostics
task_id = get_device_diagnostics("ABCDEF-Router-12345")
print(f"Diagnostic task created: {task_id}")
```

### Run Speed Test (if supported)

```python
def initiate_speed_test(device_id):
    """Initiate download/upload diagnostics"""
    
    task = {
        "type": "set_params",
        "parameters": {
            "values": {
                "InternetGatewayDevice.DownloadDiagnostics.DiagnosticsState": "Requested",
                "InternetGatewayDevice.DownloadDiagnostics.DownloadURL": "http://speedtest.example.com/download",
                "InternetGatewayDevice.UploadDiagnostics.DiagnosticsState": "Requested",
                "InternetGatewayDevice.UploadDiagnostics.UploadURL": "http://speedtest.example.com/upload"
            }
        }
    }
    
    response = requests.post(
        f"http://localhost:8080/api/devices/{device_id}/tasks",
        json=task
    )
    return response.json()
```

## Integration Examples

### Slack Notifications

```python
import requests

SLACK_WEBHOOK = "https://hooks.slack.com/services/YOUR/WEBHOOK/URL"

def notify_device_offline(device_id, device_info):
    """Send Slack notification when device goes offline"""
    
    message = {
        "text": f"🔴 Device Offline Alert",
        "attachments": [{
            "color": "danger",
            "fields": [
                {"title": "Device ID", "value": device_id, "short": True},
                {"title": "Model", "value": device_info['product_class'], "short": True},
                {"title": "Last Seen", "value": device_info['last_inform'], "short": False}
            ]
        }]
    }
    
    requests.post(SLACK_WEBHOOK, json=message)

# Example: Monitor and alert
def check_and_alert():
    response = requests.get("http://localhost:8080/api/devices")
    devices = response.json()
    
    from datetime import datetime, timedelta
    threshold = datetime.utcnow() - timedelta(hours=1)
    
    for device in devices:
        if device['last_inform']:
            last_seen = datetime.fromisoformat(device['last_inform'])
            if last_seen < threshold and device['online']:
                # Device marked online but hasn't checked in
                notify_device_offline(device['id'], device)
```

### Email Notifications

```python
import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

def send_device_alert_email(device_id, issue, recipient):
    """Send email alert about device issue"""
    
    sender = "acs@example.com"
    
    msg = MIMEMultipart()
    msg['From'] = sender
    msg['To'] = recipient
    msg['Subject'] = f"TR-069 Device Alert: {device_id}"
    
    body = f"""
    Device Alert
    
    Device ID: {device_id}
    Issue: {issue}
    Time: {datetime.utcnow().isoformat()}
    
    Please check the ACS dashboard for more details.
    """
    
    msg.attach(MIMEText(body, 'plain'))
    
    # Send email (configure your SMTP server)
    server = smtplib.SMTP('smtp.example.com', 587)
    server.starttls()
    server.login(sender, "password")
    server.send_message(msg)
    server.quit()
```

### Grafana/Prometheus Integration

```python
from prometheus_client import start_http_server, Gauge, Counter

# Define metrics
device_count = Gauge('tr069_devices_total', 'Total number of devices')
online_devices = Gauge('tr069_devices_online', 'Number of online devices')
pending_tasks = Gauge('tr069_tasks_pending', 'Number of pending tasks')
inform_count = Counter('tr069_inform_total', 'Total Inform messages received')

def update_metrics():
    """Update Prometheus metrics"""
    response = requests.get("http://localhost:8080/api/stats")
    stats = response.json()
    
    device_count.set(stats['total_devices'])
    online_devices.set(stats['online_devices'])
    pending_tasks.set(stats['pending_tasks'])

# Start Prometheus metrics server
start_http_server(9090)

# Update metrics periodically
import time
while True:
    update_metrics()
    time.sleep(30)
```

### Web Dashboard Integration

```javascript
// React component example
import React, { useState, useEffect } from 'react';

function DeviceDashboard() {
    const [devices, setDevices] = useState([]);
    const [stats, setStats] = useState({});
    
    useEffect(() => {
        // Fetch devices
        fetch('http://localhost:8080/api/devices')
            .then(res => res.json())
            .then(data => setDevices(data));
        
        // Fetch stats
        fetch('http://localhost:8080/api/stats')
            .then(res => res.json())
            .then(data => setStats(data));
        
        // Refresh every 30 seconds
        const interval = setInterval(() => {
            fetch('http://localhost:8080/api/devices')
                .then(res => res.json())
                .then(data => setDevices(data));
        }, 30000);
        
        return () => clearInterval(interval);
    }, []);
    
    const rebootDevice = (deviceId) => {
        fetch(`http://localhost:8080/api/devices/${deviceId}/reboot`, {
            method: 'POST'
        })
        .then(res => res.json())
        .then(data => alert(`Reboot scheduled: ${data.message}`));
    };
    
    return (
        <div>
            <h1>TR-069 Device Dashboard</h1>
            <div className="stats">
                <div>Total: {stats.total_devices}</div>
                <div>Online: {stats.online_devices}</div>
                <div>Offline: {stats.offline_devices}</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Device ID</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    {devices.map(device => (
                        <tr key={device.id}>
                            <td>{device.id}</td>
                            <td>{device.online ? '🟢' : '🔴'}</td>
                            <td>
                                <button onClick={() => rebootDevice(device.id)}>
                                    Reboot
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
```

## Conclusion

These examples cover the most common TR-069 ACS operations. You can combine and modify these patterns to fit your specific use case. The key is understanding the task-based architecture:

1. Create a task via the API
2. Task is stored as "pending"
3. Device connects and receives the task
4. Device executes and responds
5. Task marked as "completed"

All operations follow this pattern, making it easy to build reliable device management workflows.
