# CLAUDE.md - Project Context & Current State

**Last Updated**: November 28, 2025
**Current Focus**: TR-181 Nokia Beacon G6 WiFi task handling, task timeout verification system

## Current Status

### What's Working ✅
- **Core TR-069 CWMP**: Full implementation with TR-098 and TR-181 support
- **Device Management**: 9 devices currently connected (12,798 planned)
- **User Authentication**: Laravel Breeze with Blade templates and dark mode
- **Role-Based Access Control**: Admin, User, and Support roles with middleware protection
- **User Management**: Full CRUD interface for admins to manage users
- **Password Enforcement**: Mandatory password change on first login
- **API Authentication**: Laravel Sanctum for SPA-style API authentication
- **Smart Parameter Discovery**: "Get Everything" with optimized per-data-model approach (TR-098: single GPV, TR-181: chunked discovery)
- **Parameter Search**: Live search with 300ms debounce across 5,000+ parameters
- **CSV Export**: Streaming export with search filter support
- **Configuration Backup/Restore**: Full backup and restore functionality
- **Port Forwarding (NAT)**: Comprehensive port mapping management
- **WiFi Scanning**: Interference detection and channel analysis
- **WiFi Configuration**: Full WiFi management with verification system for slow devices
- **Enhanced Refresh**: Device refresh surpassing USS capabilities
- **Firmware Management**: Upload and deploy firmware updates
- **Dashboard**: Real-time device overview and statistics with proper authentication
- **Task Queue**: Asynchronous device operations with status tracking and smart timeouts
- **Theme Switcher**: Dark/light mode toggle in navigation
- **Subscriber Management**: Import from CSV, link devices by serial number
- **Background Job Processing**: Queue-based imports with progress tracking

### Beacon G6 Debugging - RESOLVED ✅

**Issues Encountered & Solutions**:
1. ✅ FIXED: Apache was redirecting all HTTP → HTTPS (including /cwmp)
   - **Solution**: Removed Apache VirtualHost HTTP→HTTPS redirect

2. ✅ FIXED: .htaccess was using REQUEST_URI which changes during internal rewrites
   - **Solution**: Updated .htaccess to use `THE_REQUEST` instead of `REQUEST_URI`
   - `THE_REQUEST` contains original request line and doesn't change with internal rewrites
   - Regex: `!^POST\ /cwmp[\s?]` and `!^GET\ /cwmp[\s?]`

3. ✅ FIXED: IP restrictions were blocking device connections
   - **Problem**: .htaccess only allowed 163.182.0.0/16 range
   - **Solution**: Added additional IP ranges for customer devices
   - Added: 23.155.130.0/24 and 104.247.100.0/24

**Connected Devices** (as of Nov 18, 2025):
Currently: **9 devices** across 3 manufacturers
- **Calix**: 5 devices (854G, 844G, GS4220E, and others)
- **SmartRG**: 3 devices (505n, 515ac, 516ac)
- **Nokia/ALCL**: 1 device (Beacon G6)

**Planned Deployment Scale**: 12,802 total devices
- Calix: 7,278 devices (56.85%)
- Nokia/Alcatel-Lucent: 5,153 devices (40.25%)
- Sagemcom: 213 devices (1.66%)
- SmartRG: 115 devices (0.90%)
- CIG Shanghai: 42 devices (0.33%)
- Comtrend: 1 device (0.01%)

**Note**: Devices will migrate to either this ACS or Nokia Corteca (decision pending)

**Current Configuration**:
- `/cwmp` endpoint accepts both HTTP and HTTPS
- All other endpoints redirect HTTP → HTTPS
- IP whitelisting: 6 /16 CIDR blocks (~393,216 IPs) plus full access to /cwmp
- Authentication: acs-user / acs-password
- Periodic inform: 600 seconds (10 minutes) - optimized for production scale

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
            Require ip 163.182.0.0/16
            Require ip 104.247.0.0/16
            Require ip 45.59.0.0/16
            Require ip 136.175.0.0/16
            Require ip 206.130.0.0/16
            Require ip 23.155.0.0/16
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

### Calix Devices (Fully Operational)
- **844E-1 (ENT)**: 2,834 devices planned - TR-181
- **GS4220E (GigaSpire u6)**: 2,143 devices planned - TR-181
- **854G-1 (ONT)**: 512 devices planned - TR-181
- **804Mesh (AP)**: 816 devices planned - TR-181
- **GigaMesh u4m (AP)**: 741 devices planned - TR-181
- **844G-1 (ONT)**: 227 devices planned - TR-181
- **812G-1 (ONT)**: 1 device planned - TR-181
- **Status**: All models tested and working
- **Get Everything**: ~7-8 minutes discovery time on fiber

### Sagemcom Devices (Branded as SmartRG) (Fully Operational)
- **SR505N**: 138 devices planned - TR-098 - Manufacturer: Sagemcom
- **SR515ac**: 74 devices planned - TR-098 - Manufacturer: Sagemcom
- **SR501**: 1 device planned - TR-098 - Manufacturer: Sagemcom
- **Status**: All models tested and working
- **Get Everything**: ~2-3 minutes on DSL connection
- **Success Rate**: 91% parameter retrieval

### SmartRG Devices (Fully Operational)
- **SR516ac**: 115 devices planned - TR-098
- **Status**: Tested and working
- **Get Everything**: ~2-3 minutes on DSL connection

### Nokia/Alcatel-Lucent Devices (Fully Operational)
- **Beacon G6**: 3,760 devices planned - TR-098 - **OUI: 80AB4D**
- **Beacon 2 (AP)**: 706 devices planned - TR-098
- **Beacon 3.1/3.1.1 (AP)**: 685 devices planned - TR-098
- **Beacon 24**: 2 devices planned - TR-098
- **Status**: Beacon G6 tested and working
- **Data Model**: TR-098 (InternetGatewayDevice)
- **Get Everything**: ~15 seconds using GPV with partial path (7,771 parameters)
- **Note**: Requires Apache `KeepAliveTimeout 60` for large responses

### CIG Shanghai Devices (Infrastructure)
- **XS-2426X-A**: 42 managed switches - **OUI: A08966, CCCF83**
- **Status**: TR-098 capable, may not require full management
- **Use Case**: Network infrastructure switches

### Comtrend Devices
- **NexusLink 3120**: 1 device - **OUI: D8B6B7**
- **Status**: Not yet tested

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
npm run build  # Rebuild Vite assets
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force  # Run migrations (includes role column, authentication tables)
```

### Post-Deployment Verification
```bash
# Verify routes are accessible
php artisan route:list

# Check user authentication is working
php artisan tinker --execute="
echo 'Users: ' . App\Models\User::count() . PHP_EOL;
echo 'Admin users: ' . App\Models\User::where('role', 'admin')->count() . PHP_EOL;
"

# Test login page accessibility
curl -I https://hayacs.hay.net/login

# Verify assets are built
ls -la public/build/manifest.json
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

## Authentication & Authorization

### Authentication System
**Stack**: Laravel Breeze with Blade templates
- **Template Engine**: Blade (not Inertia/React)
- **Styling**: Tailwind CSS with dark mode support
- **Features**: Login, logout, password reset, email verification

### Role-Based Access Control (RBAC)
**Roles**:
- **Admin**: Full system access including user management, device types, firmware management
- **Support**: Device management and troubleshooting capabilities
- **User**: Basic device viewing and analytics access

**Implementation**:
```php
// User model helper methods
$user->isAdmin()           // Returns true if role is 'admin'
$user->isSupport()         // Returns true if role is 'support'
$user->isAdminOrSupport()  // Returns true if role is 'admin' or 'support'
```

**Middleware Protection**:
- `admin` - Restricts route to admin users only
- `admin.support` - Restricts route to admin or support users
- Applied to route groups in `routes/web.php`

### Password Enforcement
**First Login Flow**:
1. Admin creates user with temporary password
2. `must_change_password` flag set to true
3. User forced to `/change-password` route on first login
4. Cannot access system until password changed
5. `EnsurePasswordChanged` middleware enforces this

**Password Requirements**:
- Minimum 8 characters
- Confirmation required
- Laravel's default password validation rules

### API Authentication
**Stack**: Laravel Sanctum for SPA authentication
- Protected with `auth:sanctum` middleware
- All API routes require authentication
- Token-based authentication for API clients

### Admin User Credentials
**Default Admin Account**:
- **Email**: marcel@haymail.ca
- **Role**: admin
- **Initial Setup**: Created via database seeder
- **Temporary Password**: Set by admin, requires change on first login

**Creating Admin Users**:
```bash
php artisan tinker
$user = App\Models\User::where('email', 'user@example.com')->first();
$user->password = bcrypt('TempPassword123!');
$user->role = 'admin';
$user->must_change_password = true;
$user->save();
```

### User Management Interface
**Location**: `/users` (Admin only)

**Features**:
- **List Users**: Paginated table with role badges and status indicators
- **Create User**: Form with name, email, role, password, and enforcement option
- **Edit User**: Update user details, optionally change password
- **Delete User**: Remove users (cannot delete yourself)
- **Role Descriptions**: Inline help text explaining each role's permissions

**Security**:
- Self-deletion prevention
- Password confirmation on creation
- Optional password enforcement toggle
- Role-based visibility in navigation

## Architecture Notes

### TR-069 Flow
1. Device POSTs SOAP Inform to `/cwmp`
2. `CwmpBasicAuth` middleware validates credentials
3. `CwmpController@handle` processes SOAP request
4. `CwmpService` handles SOAP/XML parsing and response
5. Device/Parameter models store data
6. Response sent back to device

### Web Authentication Flow
1. User navigates to protected route
2. `auth` middleware checks authentication
3. If authenticated, `EnsurePasswordChanged` middleware checks `must_change_password`
4. If password change required, redirect to `/change-password`
5. Role-based middleware (`admin`, `admin.support`) checks permissions
6. Route handler processes request

### Key Classes
**TR-069**:
- `app/Http/Controllers/CwmpController.php` - Main TR-069 endpoint
- `app/Services/CwmpService.php` - SOAP/XML handling
- `app/Http/Middleware/CwmpBasicAuth.php` - Device authentication
- `app/Models/Device.php` - Device model
- `app/Models/Parameter.php` - Parameter storage
- `app/Models/Task.php` - Task queue

**Authentication**:
- `app/Models/User.php` - User model with role helpers
- `app/Http/Controllers/UserController.php` - User CRUD operations
- `app/Http/Controllers/PasswordChangeController.php` - Password enforcement
- `app/Http/Middleware/EnsureUserIsAdmin.php` - Admin-only middleware
- `app/Http/Middleware/EnsureUserIsAdminOrSupport.php` - Support-level middleware
- `app/Http/Middleware/EnsurePasswordChanged.php` - Password change enforcement

## Recent Feature Additions

### Get Everything (Smart Parameter Discovery)
- **Data Model Optimized**: Uses different approaches based on device data model
- **TR-098 (Nokia, SmartRG)**: Uses `GetParameterValues` with partial path (e.g., `InternetGatewayDevice.`)
  - Returns ALL parameters with values in a single response (~15 seconds, 7,771 params on Beacon G6)
  - Matches USS behavior - fast and efficient
- **TR-181 (Calix)**: Uses `GetParameterNames` discovery then chunked `GetParameterValues`
  - ~7-8 minutes discovery time on fiber
- **Progress Tracking**: Real-time task status monitoring
- **Multi-Vendor Support**: Works with Calix, SmartRG, Nokia devices

### Smart Parameter Search
- **Live Search**: 300ms debounce for instant results
- **Dual Search**: Searches both parameter names and values
- **Large Dataset**: Handles 5,000+ parameters efficiently
- **API Integration**: Backend search with Laravel query builder
- **UI Integration**: Alpine.js reactive component

### CSV Export
- **Streaming Export**: Memory-efficient generation for large datasets
- **Filter Support**: Exports all parameters or search-filtered results
- **Smart Naming**: Includes serial number and timestamp in filename
- **Full Metadata**: Name, value, type, and last updated timestamp

### WiFi Interference Scanning
- Channel analysis and interference detection
- Signal strength monitoring
- Neighbor network discovery

### Port Forwarding Management
- Full NAT/port mapping CRUD operations
- Direct TR-069 parameter manipulation
- List, create, edit, delete mappings
- **Auto-refresh UI**: Port mappings table auto-refreshes when tasks complete
- **Database cleanup**: Deleted port mappings are automatically removed from local database
- **SmartRG optimizations**: Handles "one task per session" device limitation (see below)

### Configuration Backup/Restore
- **Manual Backups**: Create snapshots on demand
- **Auto Backups**: First-access backup creation
- **Full State**: Stores all parameters with metadata
- **Restore Capability**: Roll back to any previous configuration
- **Metadata Tracking**: Name, description, parameter count, timestamps

### Enhanced Device Refresh
- Comprehensive troubleshooting data retrieval
- Surpasses USS capabilities
- Updates local database with latest device state

## Production Scaling Recommendations

### Inform Interval Strategy
**Current**: 600 seconds (10 minutes)
- **Development/Testing**: 300-600 seconds (5-10 minutes)
- **Production (12K+ devices)**: 900-1800 seconds (15-30 minutes)
- **Impact at 12,802 devices**:
  - 10 min intervals: ~21 informs/second
  - 15 min intervals: ~14 informs/second
  - 30 min intervals: ~7 informs/second

### Infrastructure Requirements
**Database**:
- Estimated 64M parameters (5,000 params × 12,802 devices)
- Recommend: 50-100GB minimum storage
- MySQL with proper indexing on device_id and parameter name

**Storage**:
- Backups: 2-5GB per full snapshot
- Plan for multiple backup versions per device
- Consider backup retention policies

**Bandwidth**:
- Each inform: ~5-10KB
- Daily traffic at 10-min intervals: ~180-360GB
- Network capacity planning critical

### Deployment Strategy
1. **Phased Rollout**: Start with largest device models (Beacon G6, 844E-1)
2. **Firmware Tracking**: Multiple versions per model require version management
3. **Backup Strategy**: Auto-backup on first connection, manual snapshots on demand
4. **Monitoring**: Track task completion rates, failed operations, parameter anomalies

## Known Issues & Limitations

1. **HTTP Only for /cwmp**: Currently /cwmp must be HTTP (not HTTPS) due to device SSL certificate validation issues
2. **IP Restrictions**: Dashboard only accessible from whitelisted IP ranges
3. **Connection Request**: Fully implemented (HTTP with digest auth, UDP for STUN devices, "Connect Now" button in UI)
4. **Parameter Retrieval**: ~9% of parameters may fail retrieval (device-specific, normal behavior)

## Device-Specific Behaviors

### SmartRG/Sagemcom "One Task Per Session" Limitation
SmartRG devices (SR505N, SR515ac, SR516ac) only process ONE TR-069 RPC per CWMP session. If multiple commands are sent in the same session, only the first is executed.

**Impact**: Port forwarding "Add" requires two operations:
1. `AddObject` - Creates the port mapping instance
2. `SetParameterValues` - Configures the port mapping details

**Solution Implemented**:
- Follow-up tasks are created with `wait_for_next_session = true` flag
- Tasks created within 3 seconds are skipped (prevents same-session execution)
- A 4-second delay is added before sending connection request after AddObject
- This ensures the device connects AFTER the task is old enough to be sent

**Flow**:
1. User clicks "Add Port Forward"
2. `AddObject` task created and sent immediately
3. Device responds with new instance number
4. `SetParameterValues` task created with `wait_for_next_session = true`
5. System waits 4 seconds, then sends connection request
6. Device connects ~2 seconds later (task is now ~6 seconds old)
7. `SetParameterValues` passes the 3-second check and executes
8. Total time: ~8 seconds (vs waiting for periodic inform ~10 minutes)

**Key Files**:
- `CwmpController.php:handleAddObjectResponse()` - Creates follow-up task, delays connection request
- `CwmpController.php:getNextPendingTask()` - Checks `wait_for_next_session` flag and task age
- `DeviceController.php:addPortMapping()` - Creates initial AddObject task

**Abandoned Task Detection**:
- Tasks in 'sent' status are marked as abandoned if device starts a new session without responding
- 10-second grace period prevents false positives during normal session flow
- Abandoned tasks show: "Device started new TR-069 session without responding to command"

### Nokia Beacon G6 TR-098 GetParameterValues Limitations
Nokia Beacon G6 devices in TR-098 mode do NOT support GetParameterValues with partial path prefixes (paths ending in `.`).

**Problem**: Queries like `GetParameterValues("InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.")` fail silently - the device starts a new CWMP session without responding to the command.

**Working Alternative**: Use explicit full parameter names with the `{"names": [...]}` format:
```php
$wanParams = [
    'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress',
    'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.DefaultGateway',
    'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.DNSServers',
    // ... more specific params
];
$task->parameters = ['names' => $wanParams];
```

**Impact on "Get Everything"**: The `GetParameterNames` RPC also fails on this device. As a result:
- "Get Everything" feature does not work for TR-098 Nokia devices
- Must use specific parameter retrieval tasks instead
- Current workaround: Pre-defined parameter lists for key device info

**Key TR-098 WAN Parameters**:
- `InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.DefaultGateway`
- `InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.DNSServers`
- `InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress`
- `InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.SubnetMask`
- `InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ConnectionStatus`

**Note**: This limitation applies to TR-098 mode Beacon G6 devices. TR-181 mode Beacon G6 devices (identified by OUI `0C7C28`) work correctly with partial path queries.

### Nokia Beacon G6 TR-181 WiFi Task Verification System

TR-181 Nokia Beacon G6 devices take approximately **2.5 minutes per radio** to apply WiFi configuration changes. During this time, the device does not respond to TR-069 commands, which causes tasks to appear "stuck" even though they're successfully being applied.

**Problem**: WiFi configuration tasks were being marked as "failed" due to timeout, even when the device successfully applied the settings.

**Solution Implemented**: Smart WiFi verification system with the following flow:

1. **WiFi task sent** to device (set_parameter_values with WiFi parameters)
2. **Device processes** changes (~2.5 minutes per radio, doesn't respond to TR-069)
3. **After 3 minutes timeout**, instead of immediately failing:
   - Task status changes to `verifying`
   - A verification task (get_params) is queued to read back WiFi parameters
4. **When device connects again**, verification task executes
5. **Verification compares** expected vs actual values:
   - **Skips write-only fields** (passwords, passphrases return empty for security)
   - If **80%+ match** → marks original task as `completed` ✅
   - If **<80% match** → marks original task as `failed` with mismatch details

**Task Status Flow**:
```
pending → sent → verifying → completed/failed
                    ↓
            (verification task queued)
```

**Write-Only Parameters Skipped**:
- `*Passphrase*` - WiFi passwords
- `*Password*` - Any password fields
- `*PreSharedKey*` - WPA keys
- `*Key.*` - Encryption keys

**Configuration**:
- **Task-type-specific timeouts** in `TimeoutStuckTasks.php`:
  - `set_parameter_values`: 3 minutes (WiFi tasks get verification)
  - `download` (firmware): 20 minutes
  - `reboot`: 5 minutes
  - `factory_reset`: 5 minutes
  - `upload`: 10 minutes
  - `add_object`/`delete_object`: 3 minutes
  - Default: 2 minutes

**Key Files**:
- `app/Console/Commands/TimeoutStuckTasks.php` - Handles timeouts and queues verification
- `app/Http/Controllers/CwmpController.php` - Processes verification results

**Example Verification Result**:
```json
{
  "verified": true,
  "message": "WiFi settings verified successfully (92.9% match, 3 write-only skipped)",
  "matched": 13,
  "mismatched": 1,
  "missing": 0,
  "skipped": 3,
  "verification_task_id": 11731
}
```

**Recommended Device Settings**:
- Set periodic inform interval to **900 seconds (15 minutes)** for TR-181 Beacon G6 devices
- This prevents the device from sending periodic informs while processing WiFi changes

## Troubleshooting

### Common Issues Encountered

#### 1. 500 Internal Server Error - Config File Permissions
**Symptoms**: Site returns 500 error, Apache logs show "Failed opening required config file"

**Root Cause**: Config files in `/var/www/hayacs/config/` have restrictive permissions (600) that prevent Apache from reading them

**Solution**:
```bash
# Fix config file permissions
chmod 644 /var/www/hayacs/config/*.php

# Rebuild config cache
php artisan config:cache
```

**Prevention**: Ensure all config files have 644 permissions after deployment

---

#### 2. Route Not Defined Errors
**Symptoms**: `RouteNotFoundException` when accessing certain pages

**Root Cause**: Routes not registered in `routes/web.php` or route names don't match view references

**Solution**:
```bash
# Clear route cache
php artisan route:clear

# Rebuild route cache
php artisan route:cache

# Verify routes exist
php artisan route:list | grep [route-name]
```

**Check**:
- Route is defined in `routes/web.php`
- Route name matches what's used in views: `route('route.name')`
- Middleware is properly configured for the route

---

#### 3. Undefined Variable $slot - Layout Compatibility
**Symptoms**: `ErrorException - Undefined variable $slot` in layouts/app.blade.php

**Root Cause**: Laravel Breeze uses component syntax (`{{ $slot }}`), existing views use inheritance syntax (`@extends`, `@yield`)

**Solution**: Modify `resources/views/layouts/app.blade.php` to support both:
```php
<main>
    @isset($slot)
        {{ $slot }}
    @else
        @yield('content')
    @endisset
</main>
```

**Explanation**:
- Component syntax: Used by Breeze (x-guest-layout, x-app-layout)
- Inheritance syntax: Used by dashboard views (@extends('layouts.app'))
- The `@isset($slot)` check supports both patterns

---

#### 4. Layout Styling Issues After Breeze Installation
**Symptoms**: Content full-width, buttons unstyled, theme switcher missing

**Root Cause**: Laravel Breeze installation overwrites `app.blade.php` with minimal template

**Solution**:
```bash
# Rebuild Vite assets
npm run build

# Or for development
npm run dev
```

**Restore in app.blade.php**:
1. Add container classes: `max-w-7xl mx-auto sm:px-6 lg:px-8`
2. Add vertical spacing: `py-12`
3. Re-add theme switcher component: `<x-theme-switcher />`

---

#### 5. Login Password Not Working
**Symptoms**: Cannot login with expected credentials

**Solution**: Reset password via Tinker:
```bash
php artisan tinker --execute="
\$user = App\Models\User::where('email', 'user@example.com')->first();
\$user->password = bcrypt('NewPassword123!');
\$user->must_change_password = true;
\$user->save();
"
```

**Verification**:
```bash
php artisan tinker --execute="
\$user = App\Models\User::where('email', 'user@example.com')->first();
echo 'User: ' . \$user->name . PHP_EOL;
echo 'Role: ' . \$user->role . PHP_EOL;
echo 'Must change: ' . (\$user->must_change_password ? 'Yes' : 'No') . PHP_EOL;
"
```

---

#### 6. Assets Not Loading or Styling Broken
**Symptoms**: Styles not applied, JavaScript not working, 404 errors for assets

**Solution**:
```bash
# Clear all caches
php artisan cache:clear
php artisan view:clear
php artisan config:clear

# Rebuild assets
npm run build

# For production, ensure public/build directory exists
ls -la public/build/
```

**Check**:
- Vite manifest exists: `public/build/manifest.json`
- APP_ENV is set correctly in `.env`
- Vite directives in layout: `@vite(['resources/css/app.css', 'resources/js/app.js'])`

## Testing

### Device Simulator
```bash
cd /var/www/hayacs
php simulate-device.php --tr181
```

### Local Development
The project can be run locally using Laravel Herd on Windows. The local environment uses SQLite for development while production uses MySQL.

## Deployment Scale Overview

### Current Status (Nov 18, 2025)
- **Devices Connected**: 9 devices (testing phase)
- **Manufacturers**: Calix (5), SmartRG (3), Nokia (1)
- **Features Tested**: Get Everything, search, export, backup/restore all working

### Planned Production Deployment
**Total Devices**: 12,802

**Migration Path**: Devices will transition from NISC USS to either:
- This Hay ACS implementation, OR
- Nokia Corteca ACS
- Decision pending based on testing and evaluation

#### Device Breakdown by Manufacturer:

**Calix** (7,278 devices - 56.85%):
- 844E-1 (ENT): 2,834 devices - firmware 12.2.12.9.1 (2,829), 12.2.13.0.49 (5)
- GS4220E (GigaSpire u6): 2,143 devices - firmware 23.4.0.1.128 (2,114), others (29)
- 804Mesh (AP): 816 devices - firmware 3.0.3.102 (815), 2.0.1.110 (1)
- GigaMesh u4m (AP): 741 devices - firmware 23.4.0.1.115
- 854G-1 (ONT): 512 devices - firmware 12.2.12.8.4
- 844G-1 (ONT): 227 devices - firmware 12.2.12.8.4 (226), 12.2.13.0.49 (1)
- 812G-1 (ONT): 5 devices - firmware 12.2.12.8.4

**Nokia/Alcatel-Lucent** (5,153 devices - 40.25%):
- Beacon G6: 3,760 devices - firmware 3FE49996IJLJ03 (3,682), others (78) - **OUI: 80AB4D**
- Beacon 2 (AP): 706 devices - firmware 3FE49334IJLJ07 (705), 3FE49334IJKL09 (1)
- Beacon 3.1/3.1.1 (AP): 685 devices - multiple firmware versions
- Beacon 24: 2 devices

**Sagemcom** (213 devices - 1.66%):
- SR505N: 138 devices - firmware 2.6.2.6 (branded as SmartRG)
- SR515ac: 74 devices - firmware 2.6.2.7 (63), 2.6.2.6 (11) (branded as SmartRG)
- SR501: 1 device - firmware 2.6.2.6 (branded as SmartRG)

**SmartRG** (115 devices - 0.90%):
- SR516ac: 115 devices - firmware 2.6.2.6 (105), 2.6.2.7 (10)

**CIG Shanghai** (42 devices - 0.33%):
- XS-2426X-A: 42 managed switches - **OUI: A08966** (38), **OUI: CCCF83** (4)
- Note: Network infrastructure switches, may not require full TR-069 management

**Comtrend** (1 device - 0.01%):
- NexusLink 3120: 1 device - **OUI: D8B6B7**

### Key Insights
- **Root vs AP**: ~8,400 root devices, ~4,400 access points
- **Firmware Diversity**: Multiple versions per model require tracking
- **Largest Models**: Nokia Beacon G6 (3,760), Calix 844E-1 (2,834), GS4220E (2,143)
- **Primary Vendors**: Calix (56.85%) for fiber infrastructure, Nokia (40.25%) for WiFi mesh
- **Manufacturer Identification**: 100% of devices identified via IEEE OUI registry
- **Testing Coverage**: Top 3 models currently in testing (Beacon G6, 844E-1, SmartRGs, Calix devices)
- **Network Switches**: 42 CIG Shanghai XS-2426X-A switches (infrastructure, may not need full TR-069)

---

## Recent Updates (November 25, 2025)

### Authentication & User Management ✅
- **Laravel Breeze Installation**: Complete authentication scaffolding with Blade templates
- **Role-Based Access Control**: Three-tier role system (Admin, Support, User)
- **User Management Interface**: Full CRUD for admins at `/users`
- **Password Enforcement**: Mandatory password change on first login
- **API Authentication**: Laravel Sanctum for API token management
- **Middleware Protection**: Custom middleware for role-based route protection
- **Layout Compatibility**: Support for both component and inheritance Blade syntax
- **Theme Support**: Dark/light mode switcher integrated into navigation

### Database Migrations
- `2025_11_24_171758_add_role_to_users_table.php` - Adds role column (admin, user, support)
- Breeze authentication tables (users, password_resets, sessions, etc.)

### Files Modified
**Controllers**:
- `app/Http/Controllers/UserController.php` - User CRUD operations
- `app/Http/Controllers/PasswordChangeController.php` - Password enforcement

**Models**:
- `app/Models/User.php` - Added role helpers and Sanctum support

**Middleware**:
- `app/Http/Middleware/EnsureUserIsAdmin.php` - Admin-only routes
- `app/Http/Middleware/EnsureUserIsAdminOrSupport.php` - Support-level routes
- `app/Http/Middleware/EnsurePasswordChanged.php` - Password enforcement

**Routes**:
- `routes/web.php` - Protected with auth middleware, role-based route groups
- `routes/api.php` - Protected with auth:sanctum middleware

**Views**:
- `resources/views/users/` - Index, create, edit views
- `resources/views/layouts/navigation.blade.php` - Admin-only links, theme switcher
- `resources/views/layouts/app.blade.php` - Dual syntax support

**Config**:
- `bootstrap/app.php` - Middleware registration, Sanctum configuration

---

**For Claude Code**: This ACS is production-ready with all major features implemented, including full authentication and user management. Current focus is preparing for phased rollout of 12,798 devices. All core features tested and working:
- TR-069 CWMP with multi-vendor support (Calix, SmartRG, Nokia)
- User authentication with role-based access control
- "Get Everything" smart parameter discovery
- Live parameter search and CSV export
- Configuration backup/restore
- Port forwarding management (full CRUD with auto-refresh)
- WiFi interference scanning
- Task queue with real-time status tracking

**Next Steps**: Test port forwarding on remaining device models (SR505n, SR515ac, Calix, Nokia), user feedback system, global search, device view consolidation.

---

## Port Forwarding Testing Status (November 25, 2025)

| Device Model | Add | Delete | Auto-Refresh | Notes |
|--------------|-----|--------|--------------|-------|
| SR516ac | ✅ | ✅ | ✅ | ~8 seconds for add, instant delete |
| SR505n | 🔄 | 🔄 | 🔄 | Testing in progress |
| SR515ac | 🔄 | 🔄 | 🔄 | Testing in progress |
| Calix (TR-181) | ⏳ | ⏳ | ⏳ | Pending testing |
| Nokia Beacon G6 | ⏳ | ⏳ | ⏳ | Pending testing |

Legend: ✅ Working | 🔄 Testing | ⏳ Pending | ❌ Issue

---

## Recent Updates (November 26, 2025)

### Subscriber Management System ✅

**Background Import Processing**:
- Large CSV imports now processed via Laravel queue jobs (no more timeouts)
- Real-time progress tracking with Alpine.js polling
- Supervisor queue worker for persistent job processing
- Import status history with success/failure tracking

**Files Created**:
- `app/Jobs/ImportSubscribersJob.php` - Background job with 1-hour timeout
- `app/Models/ImportStatus.php` - Track import progress and statistics
- `database/migrations/2025_11_26_141702_create_import_statuses_table.php`
- `hayacs-queue-worker.conf` - Supervisor configuration

**Subscriber List Enhancements**:
- Sortable columns: Customer, Name, Service Type, Devices
- Filters: Search (name/customer/account), Service Type dropdown, Has Devices filter
- Efficient device counting using `withCount('devices')`

**Subscriber Hierarchy** (Customer > Account > Agreement):
- Customer: Top-level billing entity (can have multiple accounts)
- Account: Service account under a customer
- Agreement: Contract/service agreement (equipment tied to agreements)
- Related accounts shown when viewing subscriber details
- Equipment grouped by agreement with visual separation

**Device Linking**:
- Case-insensitive serial number matching
- Links TR-069 devices to subscribers via equipment serial numbers
- 167 devices currently linked to subscribers

**Import Statistics** (as of Nov 26, 2025):
- **Subscribers**: 9,305 records
- **Equipment**: 40,242 records
- **Linked Devices**: 167 devices matched

### BOOTSTRAP 0 Process Documentation

The factory reset restore system is fully documented:

**New Device Path**:
1. Device sends BOOTSTRAP 0 Inform
2. Provisioning rules applied (set_params task created)
3. "Get Everything" queued for parameter discovery
4. Initial backup created automatically

**Factory Reset Path** (backup age > 1 minute):
1. Device sends BOOTSTRAP 0 Inform
2. System detects existing backup older than 1 minute
3. Restore task queued (excludes ManagementServer.URL and credentials)
4. Device restored to pre-reset configuration

**SmartRG Considerations**:
- One task per session limitation
- Restore tasks chunked into 50-parameter batches
- Sequential sessions required for full restore

### Nokia Beacon TR-098 → TR-181 Migration (Planning)

**Status**: Brainstorming phase - awaiting flowchart from user

**Planned Flow**:
1. Connect both TR-098 and TR-181 Beacon G6 devices for analysis
2. Run "Get Everything" on both to compare parameter trees
3. Build parameter mapping table for critical settings
4. Implement "Transitionary Backup" system for TR-098 backups
5. Factory reset to trigger TR-181 conversion
6. Convert backup from TR-098 to TR-181 format
7. Restore converted backup to TR-181 device

**Key Challenges Identified**:
| Aspect | TR-098 | TR-181 |
|--------|--------|--------|
| Root Object | `InternetGatewayDevice.` | `Device.` |
| WiFi Path | `...LANDevice.1.WLANConfiguration.` | `...WiFi.Radio.` / `...WiFi.SSID.` |
| NAT/Port Forwarding | `...WANIPConnection.1.PortMapping.` | `...NAT.PortMapping.` |
| DHCP | `...LANHostConfigManagement.` | `...DHCPv4.Server.` |

**Mitigation Strategy**:
1. Create parameter mapping table for critical settings only
2. Focus on: WiFi credentials, port forwards, trusted networks
3. Post-restore verification via Get Everything
4. Keep TR-098 backup as fallback until TR-181 restore confirmed

**Next Steps**:
- User to connect both TR-098 and TR-181 Beacon G6 devices
- Compare parameter trees to build mapping table
- Await flowchart for complete migration process

---

## Recent Updates (November 28, 2025)

### TR-181 Nokia Beacon G6 WiFi Task Verification System ✅

**Problem Solved**: TR-181 Nokia Beacon G6 devices take ~2.5 minutes per radio to apply WiFi changes. During this time, the device doesn't respond to TR-069 commands, causing tasks to timeout and be marked as "failed" even though settings were successfully applied.

**Solution Implemented**:
- **Smart timeout system** with task-type-specific timeouts
- **WiFi verification on timeout** - instead of failing, queues a get_params task to verify settings
- **Write-only field handling** - skips password/passphrase fields that return empty for security
- **80% threshold** - tasks marked as successful if 80%+ of verifiable parameters match

**Files Created/Modified**:
- `app/Console/Commands/TimeoutStuckTasks.php` - Added WiFi detection and verification queueing
- `app/Http/Controllers/CwmpController.php` - Added verification processing methods:
  - `isWifiVerificationTask()` - Detects verification tasks
  - `processWifiVerification()` - Compares expected vs actual values
  - `normalizeValueForComparison()` - Handles boolean/string normalization

**Task Status Flow**:
```
pending → sent → (timeout) → verifying → completed/failed
```

**Test Results**:
- Task 11728 (2.4GHz WiFi): 76.5% match → Failed (before write-only fix)
- Task 11729 (5GHz WiFi): **92.9% match, 3 write-only skipped → Success** ✅

**Configuration**:
- WiFi tasks: 3-minute timeout with verification
- Firmware downloads: 20-minute timeout
- Reboots/factory resets: 5-minute timeout
- Default: 2-minute timeout

**Recommended**: Set TR-181 Beacon G6 periodic inform to 900 seconds (15 minutes) to prevent interruptions during WiFi processing.

---
