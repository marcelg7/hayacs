# CLAUDE.md - Project Context & Current State

**Last Updated**: November 18, 2025
**Current Focus**: Debugging Beacon G6 device connectivity to Hay ACS

## Current Status

### What's Working ✅
- Basic TR-069 CWMP implementation complete
- Device registration and management working for existing Calix devices
- Dashboard with device overview
- Task queue system implemented
- Device simulator working for testing
- Firmware management system implemented
- Configuration backup/restore functionality
- Port forwarding (NAT) management
- WiFi interference scanning
- Refresh device functionality (enhanced beyond USS capabilities)

### What We're Currently Debugging 🔧

**Problem**: Nokia Beacon G6 device cannot connect to Hay ACS via HTTP

**Background**:
- Device at IP: 104.247.100.206
- Serial Number: ALCLFD0A7F1E
- Currently managed by NISC USS successfully
- Trying to point it to Hay ACS at: http://hayacs.hay.net/cwmp

**Issues Encountered**:
1. ✅ FIXED: Apache was redirecting all HTTP → HTTPS (including /cwmp)
2. ✅ FIXED: .htaccess syntax errors
3. 🔴 CURRENT: Still getting 404 when device POSTs to /cwmp

**Current State**:
- Production server: hayacs.hay.net (163.182.253.70)
- Laravel route exists: `POST /cwmp` → `CwmpController@handle`
- Route verified with: `php artisan route:list | grep cwmp`
- curl test still returns 404: `curl -v -X POST http://hayacs.hay.net/cwmp`

**Root Cause Hypothesis**:
The .htaccess rewrite rules are preventing requests from reaching Laravel's routing system. Need to ensure /cwmp requests are properly routed to index.php while still allowing HTTP (not forcing HTTPS redirect).

## Production Server Details

**Server**: webapps.hay.net (163.182.253.70)
**Project Path**: /var/www/hayacs
**User**: marcelg
**Web Server**: Apache 2.4.63 (AlmaLinux)
**PHP**: 8.3
**Laravel**: 12

**Key Files**:
- Apache config: `/etc/httpd/conf.d/hayacs.conf`
- SSL config: `/etc/httpd/conf.d/hayacs-le-ssl.conf`
- .htaccess: `/var/www/hayacs/public/.htaccess` (needs fixing)
- Access log: `/var/log/httpd/hayacs-access.log`
- Error log: `/var/log/httpd/hayacs-error.log`
- Laravel log: `/var/www/hayacs/storage/logs/laravel.log`

## Apache Configuration Status

### /etc/httpd/conf.d/hayacs.conf
```apache
<VirtualHost *:80>
    ServerName hayacs.hay.net
    ServerAdmin admin@haymail.ca
    DocumentRoot /var/www/hayacs/public

    <Directory /var/www/hayacs/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    <Directory /var/www/hayacs/storage>
        Require all denied
    </Directory>

    <Directory /var/www/hayacs/bootstrap/cache>
        Require all denied
    </Directory>

    ErrorLog /var/log/httpd/hayacs-error.log
    CustomLog /var/log/httpd/hayacs-access.log combined

    RewriteEngine on
    RewriteCond %{SERVER_NAME} =hayacs.hay.net
    RewriteCond %{REQUEST_URI} !^/cwmp
    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]
</VirtualHost>
```

This configuration correctly excludes /cwmp from HTTPS redirect at the Apache level.

### /var/www/hayacs/public/.htaccess (NEEDS FIXING)

**Current Issue**: The .htaccess file has incorrect rewrite rules that prevent /cwmp from reaching Laravel.

**Correct .htaccess should be**:
```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Handle X-XSRF-Token Header
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# IP Restriction - Allow only specific IPs except for /cwmp endpoint
<IfModule mod_authz_core.c>
    # Allow CWMP endpoint from any IP
    SetEnvIf Request_URI "^/cwmp" allow_cwmp=1

    # Block all except allowed IPs (unless it's the cwmp endpoint)
    <RequireAll>
        <RequireAny>
            Require ip 163.182.253.90
            Require ip 163.182.0.0/16
            Require env allow_cwmp
        </RequireAny>
    </RequireAll>
</IfModule>
```

## Next Steps (Immediate)

1. **Fix .htaccess on production** with the correct content above
2. **Test /cwmp endpoint**: `curl -v -X POST http://hayacs.hay.net/cwmp`
   - Expected: 401 Unauthorized (means Laravel is handling it)
   - Current: 404 Not Found (means Apache/htaccess blocking)
3. **Configure Beacon G6** with correct URL: `http://hayacs.hay.net/cwmp`
4. **Monitor logs** when device connects:
   ```bash
   tail -f /var/log/httpd/hayacs-access.log
   tail -f /var/www/hayacs/storage/logs/laravel.log
   ```

## Beacon G6 Provisioning Reference

When we get connectivity working, these are the parameters NISC USS sets on initial provisioning (for future "Provision Fresh" button):

### Time/NTP Configuration
- `InternetGatewayDevice.Time.LocalTimeZoneName` = "EST+5EDT,M3.2.0/2,M11.1.0/2"
- `InternetGatewayDevice.Time.NTPServer1` = "ntp.hay.net"

### Security Configuration
- `InternetGatewayDevice.TrustedNetwork.1.SourceIPRangeStart` = "163.182.253.90"
- `InternetGatewayDevice.TrustedNetwork.1.SourceIPRangeEnd` = "163.182.253.90"
- `InternetGatewayDevice.X_Authentication.WebAccount.Password` = "{SerialNumber}_stay$away"

### TR-069 Management
- `InternetGatewayDevice.ManagementServer.ConnectionRequestUsername` = "admin"
- `InternetGatewayDevice.ManagementServer.ConnectionRequestPassword` = "admin"
- `InternetGatewayDevice.ManagementServer.PeriodicInformInterval` = 82630 (seconds, ~23 hours)
- `InternetGatewayDevice.ManagementServer.PeriodicInformEnable` = true

### WAN/Service Configuration
- `InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_D0542D_ServiceList` = "TR069,INTERNET"
- `InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_ASB_COM_DmzIpHostCfg.DmzEnabled` = false
- `InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_ASB_COM_DmzIpHostCfg.InternalClient` = ""

## Device Types Supported

### Calix GigaSpire BLAST (Working)
- **Model**: u4m, u6m
- **Data Model**: TR-181 (Device:2)
- **Status**: Fully operational
- **Features**: All CWMP operations working

### Nokia Beacon G6 (In Progress)
- **Model**: Nokia WiFi Beacon G6
- **Product Class**: Beacon G6
- **Manufacturer**: ALCL
- **OUI**: 80AB4D
- **Data Model**: TR-098 (InternetGatewayDevice)
- **Status**: Connectivity being debugged

## Environment Variables

**Production .env key settings**:
```env
APP_NAME="Hay ACS"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://hayacs.hay.net

# CWMP Authentication
CWMP_USERNAME=acs-user
CWMP_PASSWORD=acs-password

# Database
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=hayacs
DB_USERNAME=hayacs_user
```

## Common Commands

### Deploy Updates
```bash
cd /var/www/hayacs
git pull origin master
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan migrate --force
```

### Clear Caches
```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear
```

### Check Logs
```bash
# Apache access log
tail -f /var/log/httpd/hayacs-access.log

# Apache error log
tail -f /var/log/httpd/hayacs-error.log

# Laravel application log
tail -f /var/www/hayacs/storage/logs/laravel.log

# Filter for specific device
tail -f /var/log/httpd/hayacs-access.log | grep "104.247.100.206"
```

### Test Endpoints
```bash
# Test /cwmp endpoint (should get 401, not 404)
curl -v -X POST http://hayacs.hay.net/cwmp

# Test with auth
curl -v -X POST http://hayacs.hay.net/cwmp \
  -u "acs-user:acs-password" \
  -H "Content-Type: text/xml"

# Check routes
php artisan route:list | grep cwmp
```

## Architecture Notes

### TR-069 Flow
1. Device POSTs SOAP Inform to `/cwmp`
2. `CwmpBasicAuth` middleware validates credentials
3. `CwmpController@handle` processes SOAP request
4. `CwmpService` handles SOAP/XML parsing and response
5. Device/Parameter models store data
6. Response sent back to device

### Key Classes
- `app/Http/Controllers/CwmpController.php` - Main TR-069 endpoint
- `app/Services/CwmpService.php` - SOAP/XML handling
- `app/Http/Middleware/CwmpBasicAuth.php` - Device authentication
- `app/Models/Device.php` - Device model
- `app/Models/Parameter.php` - Parameter storage
- `app/Models/Task.php` - Task queue

## Recent Feature Additions

### WiFi Interference Scanning
- Added endpoint to scan for WiFi interference
- Returns signal strength, channel info, interference levels
- Available at device detail page

### Port Forwarding Management
- Comprehensive NAT/port forwarding UI
- List, create, edit, delete port mappings
- Direct TR-069 parameter manipulation

### Device Refresh
- Enhanced refresh beyond USS capabilities
- Pulls comprehensive device information
- Updates local database with latest device state

### Configuration Management
- Backup current device configuration
- Restore previous configurations
- List all backups with metadata

## Known Issues & Limitations

1. **HTTP Only for /cwmp**: Currently /cwmp must be HTTP (not HTTPS) due to device SSL certificate validation issues
2. **IP Restrictions**: Dashboard only accessible from 163.182.0.0/16 network
3. **Connection Request**: Not yet implemented (ACS cannot initiate connection to device)
4. **Firmware Download**: Implemented but not fully tested with all device types

## Testing

### Device Simulator
```bash
cd /var/www/hayacs
php simulate-device.php --tr181
```

### Local Development
The project can be run locally using Laravel Herd on Windows. The local environment uses SQLite for development while production uses MySQL.

---

**For Claude Code**: When continuing this project, start by fixing the .htaccess issue to allow /cwmp requests to reach Laravel. The route exists, authentication is configured, but Apache/htaccess is blocking the request before it reaches Laravel's router.
