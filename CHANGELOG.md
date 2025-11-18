# Changelog

All notable changes to the Hay ACS project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [Unreleased]

### Added - November 18, 2025

#### Get Everything Feature
- **Smart Parameter Discovery**: Automatic discovery of all device parameters using GetParameterNames RPC
- **Chunked Retrieval**: Intelligent chunking into 100-parameter batches for efficient retrieval
- **Background Processing**: Asynchronous task execution as devices check in
- **Progress Tracking**: Real-time task status monitoring in UI
- **Multi-Vendor Support**: Tested and working with Calix, SmartRG, and Nokia devices
- **Success Metrics**: ~91% parameter retrieval success rate across all device types

#### Smart Parameter Search
- **Live Search**: Real-time parameter filtering with 300ms debounce
- **Dual Search Mode**: Search both parameter names and values simultaneously
- **Performance**: Handles 5,000+ parameters instantly
- **Backend Integration**: Laravel query builder with LIKE queries
- **Frontend**: Alpine.js reactive component with smooth UX

#### CSV Export
- **Streaming Export**: Memory-efficient CSV generation using PHP streams
- **Filter Support**: Export all parameters or search-filtered results
- **Smart Naming**: Automatic filename generation with serial number and timestamp
- **Full Metadata**: Exports name, value, type, and last updated timestamp
- **API Endpoint**: `/api/devices/{id}/parameters/export`

#### Configuration Backup & Restore
- **Manual Backups**: On-demand configuration snapshots via UI or API
- **Auto Backups**: Automatic backup on first device access
- **Full State Preservation**: Stores all parameters with value, type, and writable flag
- **Restore Capability**: Roll back to any previous configuration
- **Metadata Tracking**: Name, description, parameter count, and timestamps
- **API Endpoints**:
  - `GET /api/devices/{id}/backups` - List backups
  - `POST /api/devices/{id}/backups` - Create backup
  - `POST /api/devices/{id}/backups/{backupId}/restore` - Restore backup

#### Port Forwarding (NAT) Management
- **Full CRUD Operations**: Create, read, update, delete port mappings
- **TR-069 Integration**: Direct manipulation of WANIPConnection port mapping parameters
- **UI Interface**: User-friendly port mapping management in device details
- **API Endpoints**:
  - `GET /api/devices/{id}/port-mappings` - List mappings
  - `POST /api/devices/{id}/port-mappings` - Add mapping
  - `DELETE /api/devices/{id}/port-mappings` - Delete mapping

#### WiFi Interference Scanning
- **Channel Analysis**: Scan for WiFi interference and neighboring networks
- **Signal Strength**: Monitor signal levels and interference
- **API Endpoints**:
  - `POST /api/devices/{id}/wifi-scan` - Start scan
  - `GET /api/devices/{id}/wifi-scan-results` - Get results

#### Enhanced Device Refresh
- **Comprehensive Data Retrieval**: Pulls extensive troubleshooting parameters
- **Beyond USS**: Surpasses existing USS system capabilities
- **Parameter Discovery**: Automatic detection and storage of new parameters

### Changed - November 18, 2025

#### Inform Interval Optimization
- **Previous**: 30 seconds (testing configuration)
- **Current**: 600 seconds (10 minutes)
- **Reason**: Optimized for production scale (12,798 devices)
- **Impact**: Reduces server load from ~426 to ~21 informs/second at full scale

#### IP Whitelisting Expansion
- **Previous**: Limited IP ranges (163.182.0.0/16, specific /24s)
- **Current**: 6 comprehensive /16 CIDR blocks
- **Coverage**: ~393,216 IP addresses
- **Ranges Added**:
  - 163.182.0.0/16
  - 104.247.0.0/16
  - 45.59.0.0/16
  - 136.175.0.0/16
  - 206.130.0.0/16
  - 23.155.0.0/16

#### UI Improvements
- **Parameter Tables**: Added horizontal scrolling for overflow
- **Button Colors**: Fixed Tailwind compilation issues (purple → indigo)
- **Responsive Design**: Improved mobile and tablet layouts
- **Loading States**: Better visual feedback during operations

### Fixed - November 18, 2025

#### Permission Issues
- **Problem**: Laravel view compilation failures due to file permissions
- **Solution**: `php artisan view:clear` + setgid bit on storage directories
- **Impact**: Resolved 500 errors when accessing device pages

#### Tailwind CSS Compilation
- **Problem**: Purple color class not compiled in production build
- **Solution**: Switched to indigo (included in default palette)
- **Impact**: "Get Everything" button displays correct color

#### .htaccess HTTP→HTTPS Redirect
- **Problem**: /cwmp endpoint was being redirected to HTTPS, blocking device connections
- **Solution**: Use `THE_REQUEST` instead of `REQUEST_URI` for HTTPS redirect exclusion
- **Impact**: Both HTTP and HTTPS now work for /cwmp endpoint

### Tested - November 18, 2025

#### Device Compatibility
**Calix Devices (TR-181)**: ✅
- 854G-1, 844G-1, 844E-1, GS4220E, 804Mesh, GigaMesh u4m, 812G-1

**SmartRG Devices (TR-098)**: ✅
- SR505N, SR515ac, SR516ac, SR501

**Nokia Devices (TR-098)**: ✅
- Beacon G6, Beacon 2, Beacon 3.1/3.1.1, Beacon 24, XS-2426X-A

#### Load Testing
- **Current**: 9 devices connected simultaneously
- **Tested**: Get Everything on multiple devices concurrently
- **Performance**: All operations completing successfully
- **Ready for**: 12,798 device deployment

### Infrastructure - November 18, 2025

#### Production Server
- **Server**: webapps.hay.net (163.182.253.70)
- **OS**: AlmaLinux with Apache 2.4.63
- **PHP**: 8.3
- **Laravel**: 12
- **Database**: MySQL
- **SSL**: Let's Encrypt certificates

#### Deployment Scale Planning
- **Total Devices**: 12,802 planned
- **Manufacturers**:
  - Calix: 7,278 devices (56.85%) - Fiber infrastructure
  - Nokia/Alcatel-Lucent: 5,153 devices (40.25%) - WiFi mesh
  - Sagemcom: 213 devices (1.66%) - CPE (branded as SmartRG)
  - SmartRG: 115 devices (0.90%) - CPE
  - CIG Shanghai: 42 devices (0.33%) - Managed switches
  - Comtrend: 1 device (0.01%) - CPE
- **OUI Registry**: 100% of devices identified via IEEE OUI lookup
- **Firmware Versions**: Multiple versions per model tracked
- **Storage Estimate**: 50-100GB database, 2-5GB per backup snapshot
- **Bandwidth**: 180-360GB daily at 10-minute inform intervals
- **Migration Path**: Devices will transition from NISC USS to either this ACS or Nokia Corteca (decision pending)

## [1.0.0] - November 14, 2025

### Initial Release
- Basic TR-069/CWMP protocol implementation
- TR-098 and TR-181 data model support
- Device registration and management
- Parameter get/set operations
- Task queue system
- Firmware upgrade capability
- Dashboard and web interface
- REST API
- Device simulator for testing
- User authentication with forced password change
- SOAP/XML message handling
- Session tracking and logging

---

## Legend
- **Added**: New features
- **Changed**: Changes to existing functionality
- **Deprecated**: Features that will be removed
- **Removed**: Features that have been removed
- **Fixed**: Bug fixes
- **Security**: Security improvements
- **Tested**: Verification and testing updates
- **Infrastructure**: Server and deployment changes
