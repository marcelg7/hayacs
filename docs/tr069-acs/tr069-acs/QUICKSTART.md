# TR-069 ACS - Quick Start Guide

Get your TR-069 Auto Configuration Server running in minutes!

## 🚀 Fast Setup (3 Commands)

```bash
# 1. Run setup script
./setup.sh

# 2. Start the server
python main.py

# 3. Open browser to http://localhost:8080
```

That's it! Your ACS is now running.

## 📱 Connect Your Devices

Configure your TR-069 CPE devices with:
- **ACS URL:** `http://your-server-ip:8080/cwmp`
- **Username/Password:** (optional, not required by default)

## ✅ Test Without Real Devices

Run the device simulator:

```bash
# Single test connection
python test_device.py

# Continuous periodic Inform messages
python test_device.py continuous
```

## 🎯 Quick Operations

### Using Web Interface
Open http://localhost:8080 in your browser to:
- View all connected devices
- See real-time status
- Reboot devices
- Monitor statistics

### Using CLI Tool

```bash
# View all devices
./acs_cli.py list

# Show device details
./acs_cli.py show ABCDEF-TestRouter-TEST123456

# Get device parameters
./acs_cli.py parameters ABCDEF-TestRouter-TEST123456

# Reboot a device
./acs_cli.py reboot ABCDEF-TestRouter-TEST123456

# Get specific parameters
./acs_cli.py get ABCDEF-TestRouter-TEST123456 \
  InternetGatewayDevice.DeviceInfo.SoftwareVersion \
  InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress

# Set parameters
./acs_cli.py set ABCDEF-TestRouter-TEST123456 \
  "InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID=MyNewWiFi"

# View statistics
./acs_cli.py stats

# View tasks for a device
./acs_cli.py tasks ABCDEF-TestRouter-TEST123456
```

### Using REST API

```bash
# List devices
curl http://localhost:8080/api/devices | jq

# Get device details
curl http://localhost:8080/api/devices/ABCDEF-TestRouter-TEST123456 | jq

# Reboot device
curl -X POST http://localhost:8080/api/devices/ABCDEF-TestRouter-TEST123456/reboot

# Create task to get parameters
curl -X POST http://localhost:8080/api/devices/ABCDEF-TestRouter-TEST123456/tasks \
  -H "Content-Type: application/json" \
  -d '{
    "type": "get_params",
    "parameters": {
      "names": [
        "InternetGatewayDevice.DeviceInfo.SoftwareVersion"
      ]
    }
  }'

# Statistics
curl http://localhost:8080/api/stats | jq
```

## 🐳 Docker Deployment

```bash
# Using Docker Compose
docker-compose up -d

# Or build and run manually
docker build -t tr069-acs .
docker run -p 8080:8080 -v $(pwd)/tr069_acs.db:/app/tr069_acs.db tr069-acs
```

## 📋 What Each File Does

| File | Purpose |
|------|---------|
| `main.py` | Main FastAPI application with CWMP endpoint and REST API |
| `cwmp_server.py` | TR-069 CWMP protocol implementation (SOAP/XML) |
| `models.py` | Database models for devices, parameters, tasks |
| `config.py` | Configuration settings |
| `acs_cli.py` | Command-line management tool |
| `test_device.py` | Device simulator for testing |
| `setup.sh` | Automated setup script |

## 🎓 Common Use Cases

### 1. Mass Configuration Update

```python
import requests

devices = requests.get("http://localhost:8080/api/devices").json()

for device in devices:
    if device['online']:
        task = {
            "type": "set_params",
            "parameters": {
                "values": {
                    "InternetGatewayDevice.ManagementServer.PeriodicInformInterval": "300"
                }
            }
        }
        requests.post(
            f"http://localhost:8080/api/devices/{device['id']}/tasks",
            json=task
        )
```

### 2. Monitor Device Status

```python
import requests
from datetime import datetime, timedelta

# Get devices not seen in last hour
devices = requests.get("http://localhost:8080/api/devices").json()
offline_threshold = datetime.utcnow() - timedelta(hours=1)

for device in devices:
    if device['last_inform']:
        last_seen = datetime.fromisoformat(device['last_inform'])
        if last_seen < offline_threshold:
            print(f"⚠️  Device {device['id']} offline for >1 hour")
```

### 3. Automated Firmware Upgrades

Add to `cwmp_server.py`:

```python
def create_download(self, url: str, filetype: str = '1 Firmware Upgrade Image'):
    """Create Download RPC for firmware upgrade"""
    # Implementation for firmware download
    pass
```

Then create task:
```python
task = {
    "type": "firmware_upgrade",
    "parameters": {
        "url": "http://your-server/firmware.bin",
        "filetype": "1 Firmware Upgrade Image"
    }
}
```

## 🔒 Production Checklist

Before going to production:

- [ ] Enable HTTPS (set SSL_CERTFILE and SSL_KEYFILE in .env)
- [ ] Configure authentication (set ENABLE_AUTH=true and API_KEY)
- [ ] Switch to PostgreSQL database for better performance
- [ ] Set up proper firewall rules
- [ ] Enable connection request authentication
- [ ] Configure log rotation
- [ ] Set up monitoring and alerts
- [ ] Back up database regularly
- [ ] Review security settings

## 📊 Monitoring

The ACS provides several monitoring endpoints:

```bash
# System stats
curl http://localhost:8080/api/stats

# Health check (for load balancers)
curl http://localhost:8080/api/stats
```

## 🆘 Troubleshooting

### Devices not connecting?
1. Check firewall allows port 8080
2. Verify device can reach server IP
3. Check device ACS URL configuration
4. Review server logs

### Tasks not executing?
1. Ensure device is online
2. Check device's PeriodicInformInterval
3. Wait for next Inform cycle
4. Check task status in database

### CLI not working?
1. Ensure server is running
2. Check if port 8080 is accessible
3. Verify no firewall blocking localhost

## 📚 Next Steps

1. **Read Full Documentation:** See `README.md` for complete details
2. **Customize Configuration:** Edit `config.py` or `.env` file
3. **Extend Functionality:** Add custom RPC methods in `cwmp_server.py`
4. **Build UI Features:** Enhance the web interface in `main.py`
5. **Add Integrations:** Connect to your existing systems via REST API

## 🤝 Support

For TR-069 protocol details, refer to:
- Broadband Forum TR-069 specification
- CWMP data models (TR-098, TR-181)

## 📝 License

MIT License - Use freely for your projects!

---

**Ready to manage thousands of devices!** 🚀
