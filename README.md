# Hay ACS - TR-069 CWMP Auto Configuration Server

<p align="center">
  <img src="public/images/hay-logo.png" alt="Hay Communications" width="120">
</p>

A complete TR-069/CWMP Auto Configuration Server (ACS) implementation built with Laravel 12. Designed for managing and provisioning TR-069 compliant CPE devices (routers, modems, gateways, etc.).

## Features

### Core TR-069 Functionality
- ✅ **Full TR-069/CWMP Protocol Support** - SOAP/XML message handling
- ✅ **Dual Data Model Support** - Works with both TR-098 and TR-181 data models
- ✅ **HTTP Basic Authentication** - Secure device connections
- ✅ **Device Auto-Discovery** - Automatic device registration on first Inform
- ✅ **Task Queue System** - Asynchronous device operations with status tracking
- ✅ **Auto-Provisioning** - Rule-based automatic device configuration
- ✅ **Production Scale Ready** - Designed for 12,000+ devices

### Supported RPC Methods
- GetParameterValues / SetParameterValues
- GetParameterNames (full parameter tree discovery)
- Reboot / FactoryReset
- Download (firmware upgrades)
- Upload (configuration/log retrieval)
- AddObject / DeleteObject
- GetRPCMethods

### Subscriber Management
- 👥 **Subscriber Import** - CSV import with background processing
  - Large file support via queue jobs (no timeouts)
  - Real-time progress tracking
  - Automatic device linking by serial number
- 📊 **Subscriber Hierarchy** - Customer > Account > Agreement structure
  - Related accounts visible in subscriber details
  - Equipment grouped by agreement
- 🔗 **Device Linking** - Case-insensitive serial number matching
  - Links TR-069 devices to billing subscribers
  - Equipment-to-device relationship tracking

### Advanced Features
- 🔍 **Get Everything** - Smart parameter discovery with automatic chunking
  - Discovers all available parameters using GetParameterNames
  - Retrieves in 100-parameter chunks for efficiency
  - Background processing with progress tracking
  - ~91% success rate across all device types
- 🔎 **Smart Parameter Search** - Live search with 300ms debounce
  - Search both parameter names and values
  - Handles 5,000+ parameters instantly
- 📊 **CSV Export** - Memory-efficient streaming export
  - Export all or filtered parameters
  - Includes full metadata and timestamps
- 💾 **Configuration Backup/Restore** - Full device state snapshots
  - Manual and automatic backup creation
  - Restore to any previous configuration
  - Metadata tracking and versioning
- 🌐 **WiFi Management** - Complete WiFi configuration
  - Interference scanning and channel analysis
  - SSID and security configuration
  - Radio control (2.4GHz/5GHz)
- 🔌 **Port Forwarding** - NAT/port mapping management
  - Create, edit, delete port mappings
  - Direct TR-069 parameter manipulation
- 📡 **Enhanced Refresh** - Comprehensive device data retrieval
  - Surpasses USS capabilities
  - Troubleshooting data collection
- 🔐 **Remote Support** - Temporary SSH access for Nokia Beacon G6
  - Set device-specific temporary passwords via TR-069
  - Automatic password reset after session expiration
  - Configurable 1-24 hour sessions
- 📊 **Speed Testing** - Download speed measurement via TR-069
  - Uses TR-069 Download RPC for accurate measurements
  - Real-time progress tracking
- 📝 **Log Management** - Automated log rotation and compression
  - Daily rotation with xz compression (84%+ size reduction)
  - Configurable retention and max file sizes
- 🔄 **Device Groups & Workflows** - Batch operations (in progress)
  - Dynamic group membership by model/firmware
  - Scheduled and triggered operations

### Web Interface
- 📊 **Dashboard** - Overview of devices, statistics, and recent activity
- 🖥️ **Device Management** - View and manage all connected devices
- 📋 **Task Management** - Monitor and create device tasks with real-time status
- 🔐 **User Authentication** - Secure login with forced password change on first use
- 📱 **Responsive Design** - Works on desktop and mobile
- 🎨 **Modern UI** - Tailwind CSS with Alpine.js interactivity

### Developer Tools
- 🛠️ **Device Simulator** - Test TR-069 connections without physical devices
- 📡 **REST API** - Programmatic access to all ACS functions
- 📝 **Comprehensive Logging** - Detailed request/response logging

## Requirements

- PHP 8.3 or higher
- Composer
- Database (MySQL, PostgreSQL, or SQLite)
- Web server (Apache, Nginx, or Laravel Herd)
- Node.js & NPM (for asset compilation)

## Installation

### Local Development

1. **Clone the repository**
   ```bash
   git clone https://github.com/YOUR-USERNAME/hay-acs.git
   cd hay-acs
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   npm run build
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Update `.env` file**
   ```env
   APP_NAME="Hay ACS"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8000

   DB_CONNECTION=sqlite
   # Or use MySQL/PostgreSQL
   ```

5. **Run migrations and seed database**
   ```bash
   php artisan migrate
   php artisan db:seed --class=AdminUserSeeder
   ```

6. **Start development server**
   ```bash
   php artisan serve
   ```

7. **Access the application**
   - Dashboard: http://localhost:8000
   - Login: `marcel@haymail.ca` / `TempPassword123!` (change on first login)
   - CWMP Endpoint: http://localhost:8000/cwmp

## Configuration

### Device Connection Settings

Configure your TR-069 CPE devices with:

- **ACS URL**: `http://your-domain.com/cwmp`
- **ACS Username**: `acs-user`
- **ACS Password**: `acs-password`

> **Security Note**: Change these credentials in production by updating `app/Http/Middleware/CwmpBasicAuth.php`

### Admin User

Default admin credentials (created by seeder):
- **Email**: `marcel@haymail.ca`
- **Password**: `TempPassword123!`
- **Note**: Password change required on first login

## Usage

### Testing with Device Simulator

Test your ACS without physical devices:

```bash
# Simulate TR-181 device
php simulate-device.php --tr181

# Simulate TR-098 device
php simulate-device.php

# Custom device
php simulate-device.php --manufacturer="Acme" --oui="123456" --serial="TEST001"
```

### REST API Examples

```bash
# Get all devices
curl -X GET http://localhost:8000/api/devices

# Get device details
curl -X GET http://localhost:8000/api/devices/{device_id}

# Get all parameters for a device
curl -X GET http://localhost:8000/api/devices/{device_id}/parameters

# Search parameters
curl -X GET "http://localhost:8000/api/devices/{device_id}/parameters?search=SSID"

# Export parameters to CSV
curl -X GET "http://localhost:8000/api/devices/{device_id}/parameters/export?format=csv" > device-params.csv

# Get Everything (discover and retrieve all parameters)
curl -X POST http://localhost:8000/api/devices/{device_id}/get-all-parameters

# Get specific parameters
curl -X POST http://localhost:8000/api/devices/{device_id}/get-parameters \
  -H "Content-Type: application/json" \
  -d '{"parameters": ["Device.DeviceInfo.SoftwareVersion"]}'

# Set device parameters
curl -X POST http://localhost:8000/api/devices/{device_id}/set-parameters \
  -H "Content-Type: application/json" \
  -d '{"parameters": {"InternetGatewayDevice.Time.NTPServer1": "pool.ntp.org"}}'

# Reboot a device
curl -X POST http://localhost:8000/api/devices/{device_id}/reboot

# Create configuration backup
curl -X POST http://localhost:8000/api/devices/{device_id}/backups \
  -H "Content-Type: application/json" \
  -d '{"description": "Pre-upgrade backup"}'

# Get WiFi scan results
curl -X GET http://localhost:8000/api/devices/{device_id}/wifi-scan-results

# Get port mappings
curl -X GET http://localhost:8000/api/devices/{device_id}/port-mappings
```

## Project Structure

```
hay-acs/
├── app/
│   ├── Http/Controllers/
│   │   ├── Api/              # REST API controllers
│   │   ├── CwmpController.php # Main TR-069 endpoint
│   │   └── DashboardController.php
│   ├── Models/
│   │   ├── Device.php        # Device model
│   │   ├── Parameter.php     # Parameter storage
│   │   ├── Task.php          # Task queue
│   │   ├── CwmpSession.php   # Session tracking
│   │   ├── Subscriber.php    # Subscriber model
│   │   ├── SubscriberEquipment.php # Equipment records
│   │   └── ImportStatus.php  # Import job tracking
│   ├── Jobs/
│   │   └── ImportSubscribersJob.php # Background CSV import
│   ├── Services/
│   │   ├── CwmpService.php   # TR-069 SOAP/XML handling
│   │   └── ProvisioningService.php # Auto-provisioning
│   └── Middleware/
│       ├── CwmpBasicAuth.php # Device authentication
│       └── EnsurePasswordChanged.php
├── database/
│   ├── migrations/           # Database schema
│   └── seeders/              # Database seeders
├── resources/
│   └── views/
│       └── dashboard/        # Dashboard views
├── routes/
│   ├── web.php              # Web routes
│   └── api.php              # API routes
├── public/
│   └── images/
│       └── hay-logo.png
└── simulate-device.php      # Device simulator
```

## Database Schema

### Devices
Stores registered CPE devices with manufacturer, model, versions, and online status.

### Parameters
Flexible storage for any TR-069 parameter path (supports both TR-098 and TR-181).

### Tasks
Queue for asynchronous device operations (get/set parameters, reboot, firmware upgrade, etc.).

### CWMP Sessions
Tracks all device communication sessions for audit and debugging.

### Subscribers
Customer billing records imported from external systems (Customer > Account > Agreement hierarchy).

### Subscriber Equipment
Equipment records tied to subscriber agreements, used for device-subscriber linking via serial numbers.

### Import Status
Tracks background import job progress with real-time statistics.

## Deployment

See [DEPLOYMENT-GUIDE.md](DEPLOYMENT-GUIDE.md) for detailed production deployment instructions.

### Quick Production Checklist

1. Set environment to production in `.env`
2. Configure database (MySQL/PostgreSQL recommended)
3. Set `APP_DEBUG=false`
4. Run migrations: `php artisan migrate --force`
5. Seed admin user: `php artisan db:seed --class=AdminUserSeeder`
6. Cache config: `php artisan config:cache`
7. Cache routes: `php artisan route:cache`
8. Set proper permissions on `storage/` and `bootstrap/cache/`
9. Configure HTTPS (recommended for production)
10. Update CWMP credentials in `CwmpBasicAuth.php`

## TR-069 Data Model Support

### TR-098 (InternetGatewayDevice)
```
InternetGatewayDevice.
├── DeviceInfo.
│   ├── Manufacturer
│   ├── SoftwareVersion
│   └── HardwareVersion
├── ManagementServer.
│   └── ConnectionRequestURL
└── WANDevice.1.WANConnectionDevice.1.
```

### TR-181 (Device:2)
```
Device.
├── DeviceInfo.
│   ├── Manufacturer
│   ├── SoftwareVersion
│   └── HardwareVersion
├── ManagementServer.
│   └── ConnectionRequestURL
└── IP.Interface.1.
```

The ACS automatically detects which data model a device uses based on parameter names.

## Security Considerations

- Change default CWMP credentials (`acs-user`/`acs-password`)
- Use HTTPS in production
- Keep `APP_DEBUG=false` in production
- Protect `.env` file from public access
- Regularly update dependencies
- Consider device-specific authentication (future enhancement)

## Troubleshooting

### Device Not Connecting

1. Check device can reach ACS URL
2. Verify CWMP credentials are correct
3. Check logs: `storage/logs/laravel.log`
4. Ensure CSRF is disabled for `/cwmp` endpoint
5. Test with device simulator first

### Viewing Logs

```bash
# Recent logs
tail -100 storage/logs/laravel.log

# CWMP-specific logs
tail -100 storage/logs/laravel.log | grep CWMP

# Clear logs
echo "" > storage/logs/laravel.log
```

## Technology Stack

- **Framework**: Laravel 12
- **PHP**: 8.3
- **Authentication**: Laravel Breeze
- **Database**: SQLite (development), MySQL/PostgreSQL (production)
- **Frontend**: Tailwind CSS, Alpine.js
- **Protocol**: SOAP/XML over HTTP(S)

## Supported Devices

### Tested and Working
This ACS has been tested and verified with the following devices:

#### Calix (TR-181)
- 844E-1 (ENT) - Production ready for 2,834 devices
- GS4220E (GigaSpire u6) - Production ready for 2,143 devices
- 854G-1 (ONT) - Production ready for 512 devices
- 844G-1 (ONT) - Production ready for 227 devices
- 804Mesh (AP) - Production ready for 816 devices
- GigaMesh u4m (AP) - Production ready for 741 devices
- 812G-1 (ONT)

#### Sagemcom (TR-098) - Branded as SmartRG
- SR505N - Production ready for 138 devices
- SR515ac - Production ready for 74 devices
- SR501 - 1 device

#### SmartRG (TR-098)
- SR516ac - Production ready for 115 devices

#### Nokia/Alcatel-Lucent (TR-098)
- Beacon G6 - Production ready for 3,760 devices (OUI: 80AB4D)
- Beacon 2 (AP) - Production ready for 706 devices
- Beacon 3.1/3.1.1 (AP) - Production ready for 685 devices
- Beacon 24 - 2 devices

#### CIG Shanghai (TR-098)
- XS-2426X-A - 42 managed switches (OUI: A08966, CCCF83)
- Network infrastructure switches

#### Comtrend (TR-098)
- NexusLink 3120 - 1 device (OUI: D8B6B7)

### Production Scale
Designed and tested for deployment of **12,802 devices** across 6 manufacturers:
- **Calix**: 7,278 devices (56.85%) - Fiber infrastructure
- **Nokia/Alcatel-Lucent**: 5,153 devices (40.25%) - WiFi mesh
- **Sagemcom**: 213 devices (1.66%) - CPE (branded as SmartRG)
- **SmartRG**: 115 devices (0.90%) - CPE
- **CIG Shanghai**: 42 devices (0.33%) - Network switches
- **Comtrend**: 1 device (0.01%) - CPE

**100% manufacturer identification** via IEEE OUI registry lookup.

## Documentation

- [CLAUDE.md](CLAUDE.md) - Project context, current state, and deployment details
- [TR069-ACS-IMPLEMENTATION.md](TR069-ACS-IMPLEMENTATION.md) - Technical implementation details
- [DEPLOYMENT-GUIDE.md](DEPLOYMENT-GUIDE.md) - Deployment instructions

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## License

This project is proprietary software developed for Hay Communications.

## Support

For issues or questions, please contact:
- **Email**: marcel@haymail.ca
- **Company**: Hay Communications

---

Built with ❤️ using Laravel and Claude Code
