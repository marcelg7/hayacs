# TR-069 ACS (Auto Configuration Server)

A modern, clean TR-069 ACS implementation for managing CPE (Customer Premises Equipment) devices using the CWMP (CPE WAN Management Protocol).

## Features

✅ **Full TR-069 CWMP Protocol Support**
- SOAP/XML communication with CPE devices
- Inform message handling
- GetParameterValues / SetParameterValues
- Reboot and FactoryReset commands
- Session management

✅ **Device Management**
- Auto-discovery and registration
- Real-time device status tracking
- Parameter monitoring and storage
- Task queue system
- Device grouping with tags

✅ **REST API**
- Complete API for device management
- Task creation and monitoring
- Parameter queries
- Statistics and reporting

✅ **Web Interface**
- Beautiful, responsive dashboard
- Device list with status
- Real-time statistics
- Device control (reboot, etc.)

## Architecture

```
TR-069 ACS
├── cwmp_server.py    - CWMP protocol implementation
├── models.py         - Database models (SQLAlchemy)
├── main.py          - FastAPI application
└── requirements.txt  - Python dependencies
```

## Quick Start

### 1. Install Dependencies

```bash
pip install -r requirements.txt
```

### 2. Start the Server

```bash
python main.py
```

The server will start on `http://0.0.0.0:8080`

### 3. Access the Dashboard

Open your browser to: `http://localhost:8080`

### 4. Configure Your CPE Devices

Point your TR-069 devices to the ACS URL:
```
ACS URL: http://your-server-ip:8080/cwmp
```

## API Documentation

### Device Management

#### List All Devices
```bash
GET /api/devices
```

Response:
```json
[
  {
    "id": "ABCDEF-Router-12345",
    "manufacturer": "Vendor",
    "product_class": "Router",
    "serial_number": "12345",
    "online": true,
    "last_inform": "2025-11-12T10:30:00",
    "software_version": "1.0.0"
  }
]
```

#### Get Device Details
```bash
GET /api/devices/{device_id}
```

#### Get Device Parameters
```bash
GET /api/devices/{device_id}/parameters
```

Returns all parameters and their values for the device.

### Device Control

#### Reboot Device
```bash
POST /api/devices/{device_id}/reboot
```

#### Factory Reset Device
```bash
POST /api/devices/{device_id}/factory-reset
```

#### Create Custom Task
```bash
POST /api/devices/{device_id}/tasks
Content-Type: application/json

{
  "type": "get_params",
  "parameters": {
    "names": [
      "InternetGatewayDevice.DeviceInfo.SoftwareVersion",
      "InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress"
    ]
  }
}
```

Task Types:
- `get_params` - Get specific parameters
- `set_params` - Set parameter values
- `reboot` - Reboot device
- `factory_reset` - Factory reset

#### Get Device Tasks
```bash
GET /api/devices/{device_id}/tasks
```

### Statistics

```bash
GET /api/stats
```

Response:
```json
{
  "total_devices": 10,
  "online_devices": 8,
  "offline_devices": 2,
  "pending_tasks": 3
}
```

## Database Schema

The ACS uses SQLite by default, with the following tables:

### devices
- Device registration and status
- Connection information
- Software/hardware versions
- Tags and metadata

### parameters
- Device parameter storage
- Parameter history
- Type and writability info

### tasks
- Pending device tasks
- Task status tracking
- Results storage

### sessions
- CWMP session tracking
- Message exchange logs

## TR-069 Protocol Flow

1. **Device Connects**: CPE initiates connection to ACS
2. **Inform**: Device sends Inform message with event codes and parameters
3. **InformResponse**: ACS acknowledges Inform
4. **Task Execution**: If tasks pending, ACS sends RPC methods
5. **Response**: Device executes and responds
6. **Session End**: Empty HTTP body ends session

## Common TR-069 Parameters

```
# Device Info
InternetGatewayDevice.DeviceInfo.Manufacturer
InternetGatewayDevice.DeviceInfo.ModelName
InternetGatewayDevice.DeviceInfo.SoftwareVersion
InternetGatewayDevice.DeviceInfo.HardwareVersion
InternetGatewayDevice.DeviceInfo.SerialNumber

# WAN Connection
InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress
InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ConnectionStatus

# WiFi
InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID
InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel

# Management
InternetGatewayDevice.ManagementServer.URL
InternetGatewayDevice.ManagementServer.PeriodicInformEnable
InternetGatewayDevice.ManagementServer.PeriodicInformInterval
```

## Example Usage

### Get WiFi SSID from Device

```python
import requests

# Create task to get WiFi parameters
task = {
    "type": "get_params",
    "parameters": {
        "names": [
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID",
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel"
        ]
    }
}

response = requests.post(
    "http://localhost:8080/api/devices/ABCDEF-Router-12345/tasks",
    json=task
)

print(response.json())
```

### Change WiFi SSID

```python
# Create task to set WiFi SSID
task = {
    "type": "set_params",
    "parameters": {
        "values": {
            "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID": "NewNetworkName"
        }
    }
}

response = requests.post(
    "http://localhost:8080/api/devices/ABCDEF-Router-12345/tasks",
    json=task
)
```

## Security Considerations

For production deployment, consider:

1. **HTTPS**: Use TLS for CWMP endpoint
2. **Authentication**: Add HTTP digest auth for CPE connections
3. **API Keys**: Secure REST API with authentication
4. **Firewall**: Restrict CWMP endpoint to known CPE IPs
5. **Connection Request Auth**: Store and use CPE credentials

## Production Deployment

### Using Docker

Create a `Dockerfile`:

```dockerfile
FROM python:3.11-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

EXPOSE 8080

CMD ["python", "main.py"]
```

Build and run:
```bash
docker build -t tr069-acs .
docker run -p 8080:8080 -v $(pwd)/tr069_acs.db:/app/tr069_acs.db tr069-acs
```

### Using systemd

Create `/etc/systemd/system/tr069-acs.service`:

```ini
[Unit]
Description=TR-069 ACS
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/opt/tr069-acs
ExecStart=/usr/bin/python3 main.py
Restart=always

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl enable tr069-acs
sudo systemctl start tr069-acs
```

## Extending the ACS

### Adding Custom Device Provisioning

Edit `main.py` in the `cwmp_endpoint` function:

```python
if method == 'Inform':
    # ... existing code ...
    
    # Custom provisioning logic
    if '0 BOOTSTRAP' in params.get('events', []):
        # First time device connects
        # Auto-configure device
        provision_task = Task(
            device_id=device_id,
            task_type='set_params',
            parameters={
                'values': {
                    'InternetGatewayDevice.ManagementServer.PeriodicInformInterval': '300',
                    'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID': f'AutoSSID_{device.serial_number}'
                }
            },
            status='pending'
        )
        db.add(provision_task)
```

### Adding Firmware Updates

```python
def create_firmware_upgrade(self, url: str, file_type: str = '1 Firmware Upgrade Image'):
    """Create Download RPC for firmware upgrade"""
    envelope = ET.Element('{http://schemas.xmlsoap.org/soap/envelope/}Envelope')
    # ... namespace setup ...
    
    download = ET.SubElement(body, '{urn:dslforum-org:cwmp-1-0}Download')
    
    command_key = ET.SubElement(download, 'CommandKey')
    command_key.text = f'fw_upgrade_{datetime.utcnow().timestamp()}'
    
    file_type_elem = ET.SubElement(download, 'FileType')
    file_type_elem.text = file_type
    
    url_elem = ET.SubElement(download, 'URL')
    url_elem.text = url
    
    # ... continue building XML ...
```

## Troubleshooting

### Device Not Appearing

1. Check device TR-069 configuration
2. Verify ACS URL is correct
3. Check firewall rules
4. Review server logs
5. Verify device can reach ACS IP

### Tasks Not Executing

1. Ensure device is online
2. Check task status in database
3. Wait for next Inform cycle
4. Check device's PeriodicInformInterval

### Connection Issues

1. Verify port 8080 is open
2. Check if using correct protocol (http/https)
3. Review CPE logs for connection errors

## License

MIT License - Feel free to use and modify for your needs.

## Contributing

This is a clean, minimal implementation. Feel free to extend with:
- Authentication
- HTTPS support
- Connection Request callbacks
- File transfers
- Advanced provisioning workflows
- Device templates
- Bulk operations
- Notifications/webhooks

## Support

For TR-069 protocol specification, see:
- TR-069 Amendment 6 (Broadband Forum)
- CWMP Data Models (TR-098, TR-181)
