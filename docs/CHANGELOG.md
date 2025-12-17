# Hay ACS Changelog

Historical development updates moved from CLAUDE.md to reduce file size.
For current system status and configuration, see [CLAUDE.md](../CLAUDE.md).

---

## December 17, 2025

### Server Crash Investigation & Recovery

**Incident**: Server became unresponsive for ~10 minutes due to extremely high CPU/system load (load average peaked at 163.70).

**Root Cause**: MariaDB was killed by the Linux OOM (Out of Memory) killer.
- MariaDB memory usage: 12GB RAM + 3GB swap (on a 15GB server)
- `systemctl status mariadb` showed: `Active: failed (Result: oom-kill)`

**Contributing Factors**:
1. **InnoDB buffer pool too large** (8GB on 15GB server = 53% of RAM)
2. **Grafana MySQL dashboards** running heavy queries on auto-refresh (every 5-30 seconds)
3. **Laravel log file** had grown to 3.4GB (13.7 million lines)
4. **Backup cleanup command** was crashing nightly due to memory exhaustion

### Fixes Applied

#### 1. Laravel Log Truncated
```bash
# Was 3.4GB, truncated to 0
truncate -s 0 /var/www/hayacs/storage/logs/laravel.log
```

#### 2. Fixed Backup Cleanup Command (Memory Leak)
**Problem**: `backups:cleanup` loaded all 64K+ backups (including 280KB `backup_data` LONGTEXT) into memory, causing OOM crash every night at 11:30 PM.

**Solution**: Rewrote to use direct SQL DELETE queries with batching instead of Eloquent models.

**File**: `app/Console/Commands/CleanupOldBackups.php`

#### 3. New Smart Backup Retention Policy

| Backup Type | Old Retention | New Retention |
|-------------|---------------|---------------|
| Initial | Never deleted | Never deleted (unchanged) |
| User/Manual | 90 days | 90 days (unchanged) |
| Auto/Daily | 7 days (time-based) | **2 daily + 1 weekly** (count-based) |

**Result**:
- Deleted 42,043 excess backups
- config_backups table: 18.5GB → 9.48GB (~9GB freed)
- Total DB: 52.5GB → 43.9GB (~8.6GB freed)
- Each device now keeps: 1 initial + 2 recent daily + 1 weekly (7+ days old)

### Database Analysis Results

| Table | Size | Rows | Assessment |
|-------|------|------|------------|
| parameters | 16.4 GB | 23.3M | Normal (avg 2,212 per device) |
| tasks | 10.4 GB | 327K | Normal |
| config_backups | 9.5 GB | 29K | Optimized (was 18.5GB/71K) |
| device_events | 2.7 GB | 10M | Could cleanup (7+ days) |
| cwmp_sessions | 2.7 GB | 10.8M | Could cleanup (7+ days) |

### Server RAM Upgrade Planned

**Current**: 15GB RAM (undersized for 52GB database)

**Planned**: 48GB RAM with new InnoDB settings:
```ini
innodb_buffer_pool_size = 28G
innodb_buffer_pool_instances = 14
```

### Grafana Monitoring Documentation

Added comprehensive documentation at `/docs/monitoring`:
- `index.blade.php` - Monitoring overview
- `dashboards.blade.php` - All 8 Grafana dashboards documented
- `prometheus.blade.php` - Prometheus & exporter configuration

**Dashboards Created**:
1. Executive Summary (MySQL)
2. Task Performance (MySQL) - Gateway vs Mesh AP success rates
3. Device Health (MySQL)
4. Subscriber (MySQL)
5. Node Exporter Full (Prometheus)
6. MySQL Overview (Prometheus)
7. Apache (Prometheus)
8. PHP-FPM (Prometheus)

### Files Modified/Created

| File | Change |
|------|--------|
| `app/Console/Commands/CleanupOldBackups.php` | Rewritten for memory efficiency + smart retention |
| `app/Console/Commands/CleanupOldData.php` | New general cleanup command |
| `resources/views/docs/layout.blade.php` | Fixed code block styling (white text issue) |
| `resources/views/docs/monitoring/*.blade.php` | New monitoring documentation |
| `app/Http/Controllers/DocsController.php` | Added monitoring section |

### Remaining Tasks After Reboot

1. Update `/etc/my.cnf.d/mariadb-server.cnf`:
   ```ini
   innodb_buffer_pool_size = 28G
   innodb_buffer_pool_instances = 14
   ```
2. Restart MariaDB
3. Change Grafana MySQL dashboard refresh intervals to 5-10 minutes

### Instance 2 Session - Dashboard Features (Claude Code Instance 2)

#### LAN Configuration Editing
Added ability to edit LAN settings from the Dashboard tab:
- **Fields**: LAN IP Address, DHCP Start, DHCP End
- **Validation**: IP format, same subnet check, DHCP range validation
- **Auto-reboot**: LAN changes automatically trigger a reboot task (required for settings to take effect)

**API Endpoints**:
- `GET /api/devices/{id}/lan-config`
- `POST /api/devices/{id}/lan-config`

#### Admin Credentials Section
New section on Dashboard tab showing customer-facing admin credentials:
- **User.1** = Customer admin account (shown to customers)
- **User.2** = Support/superadmin (internal only, NOT shown)
- Show/hide password toggle with copy to clipboard
- Reset password option (random 8-char or custom)

**API Endpoints**:
- `GET /api/devices/{id}/admin-credentials`
- `POST /api/devices/{id}/admin-credentials/reset`

**Important**: Nokia TR-098 uses `InternetGatewayDevice.User.1.*` (same as Calix), NOT `X_Authentication.WebAccount` which is superadmin.

#### Admin Status Bar Fix
**Problem**: Admin bar showing all dashes (`Load: - / - / -`)

**Root Cause**: Apache's `mod_status` was intercepting `/server-status` before Laravel could handle it.

**Fix**: Changed route from `/server-status` to `/admin/system-status`

**Files Modified**:
- `routes/web.php` (line 178)
- `resources/views/layouts/navigation.blade.php` (line 136)

#### Database Migration
- `2025_12_15_141647_add_resend_count_to_tasks_table` - Took 3m 17s (contributed to server load spike)

#### Files Modified by Instance 2

| File | Changes |
|------|---------|
| `app/Http/Controllers/Api/DeviceController.php` | Added LAN config & admin credentials methods |
| `routes/api.php` | Added LAN and admin credential routes |
| `routes/web.php` | Changed `/server-status` → `/admin/system-status` |
| `resources/views/device-tabs/dashboard.blade.php` | Added LAN edit modal, Admin Credentials section |
| `resources/views/layouts/navigation.blade.php` | Updated status bar fetch URL |
| `docs/INSTANCE2_DASHBOARD_CHANGES.md` | Documentation for Instance 1 coordination |

---

## December 10, 2025

### Automated Billing System Sync

**Purpose**: Automatically sync subscriber and equipment data from NISC Ivue billing system to Hay ACS, linking TR-069 devices to subscribers by serial number.

**SFTP Configuration**:
| Setting | Value |
|---------|-------|
| Host | `webapps.hay.net` (163.182.253.70) |
| Port | `22` |
| Username | `billingsync` |
| Upload Directory | `/uploads/` |
| Chroot Jail | `/home/billingsync` |

**Hybrid Import Approach**:
1. **Subscribers**: Upsert (updateOrCreate) - IDs stay stable across syncs
2. **Equipment**: Full truncate and reimport - always fresh from billing system
3. **Device Links**: Clear and rebuild via bulk UPDATE with JOIN

**Performance**: ~53 seconds total sync time (well under 15-minute interval)

**Critical Index**: `devices_serial_number_index` on `serial_number` column - without this, device linking takes 280+ seconds.

**Files Created**:
- `app/Console/Commands/SyncBillingData.php`
- `database/migrations/2025_12_10_135829_add_serial_number_index_to_devices_table.php`

### Reports Page Performance Optimization

- Cold cache: 46s → 6.1s (7.6x faster)
- Warm cache: 1ms (instant)
- Used fulltext search instead of LIKE queries
- Added 30-minute cache for expensive queries

### Standard WiFi Setup Enhancements

- Guest password field with auto-generation option
- Advanced WiFi password storage in DeviceWifiCredential
- WPA2/WPA3 security where supported

### Subscriber Page Device Links

- TR-069 devices now clickable links to device view
- Equipment section prioritizes TR-069 links over cable portal

### Automated Initial Backup Workflow

- Device Group: "Devices Needing Initial Backup" (rule: `initial_backup_created = false`)
- Rate limited: 100 devices/hour, max 10 concurrent
- Auto-detects TR-098 vs TR-181 data model

### Device Online Status Tracking

- `devices:mark-offline` command marks devices offline after 20 minutes
- Scheduled every 5 minutes
- Current stats: 84.8% online, 15.2% offline

---

## December 5, 2025

### Global Search Performance Optimization

**Results**: Average 69ms (down from 2000-8000ms)

**Optimizations**:
1. Composite indexes on tasks table
2. Smart query filtering (exclude IPs, MACs, serial prefixes from parameter search)
3. Disabled MAC address search in global search

**Migration**: `2025_12_05_102416_add_composite_index_for_task_search_performance.php`

---

## December 2-3, 2025

### Remote Support System for Nokia Beacon G6

- Temporary SSH access via TR-069
- Automatic password reset after expiration
- Files: `ResetExpiredRemoteSupport.php`, `DeviceSshCredential.php`

### Log Management System

- Automated rotation, compression (84% reduction), retention
- Command: `php artisan logs:manage`
- Scheduled daily at 3 AM

### Nokia Beacon G6 TR-181 Migration Testing

- Successfully converted test device from TR-098 to TR-181
- OUI changes: `80AB4D` → `0C7C28`
- Pre-config file: `docs/beacon-g6-pre-config-hayacs-tr181.xml`

### Analytics Page Fix

- Moved routes from api.php to web.php for session auth
- Optimized queries to use database aggregates

### Device Groups and Workflows System

Tables: `device_groups`, `device_group_rules`, `group_workflows`, `workflow_executions`, `workflow_logs`

---

## December 1, 2025

### Calix TR-098 Standard WiFi Setup

- RadioEnabled control
- Dedicated network support (`{ssid}-2.4GHz`, `{ssid}-5GHz`)
- Performance optimizations (AirtimeFairness, MulticastForward disabled)
- Auto page refresh on WiFi task completion

---

## November 29, 2025

### Speed Test Implementation for Calix Devices

- Download via TR-069 Download RPC
- 10MB test file from speedtest.tele2.net
- Upload NOT supported on Calix (device ignores command)

### Session Management Bug Fix

- Added device ID validation in `handleFaultResponse()`

### Calix WiFi Password Reveal Feature

- Eye icon toggle for Calix devices
- Uses `X_000631_KeyPassphrase` parameter (read-only)

### Calix WiFi Parameter Structure Documentation

**TR-098 Instance Mapping** (InternetGatewayDevice.LANDevice.1.WLANConfiguration.{i}):
- Instance 1: Primary 2.4GHz
- Instance 2: Guest 2.4GHz
- Instance 3: Dedicated 2.4GHz
- Instance 9: Primary 5GHz
- Instance 10: Guest 5GHz
- Instance 12: Dedicated 5GHz

---

## November 28, 2025

### TR-181 Nokia Beacon G6 WiFi Task Verification System

**Problem**: WiFi changes take ~2.5 minutes per radio, causing false "failed" status.

**Solution**:
- Smart timeout system with task-type-specific timeouts
- WiFi verification on timeout (queues get_params to verify)
- 80% threshold for success
- Skip write-only fields (passwords)

**Task Flow**: `pending → sent → (timeout) → verifying → completed/failed`

---

## November 26, 2025

### Subscriber Management System

- Background import via Laravel queue jobs
- Real-time progress tracking
- Subscriber hierarchy: Customer > Account > Agreement
- Device linking by serial number

### BOOTSTRAP 0 Process Documentation

- New device path: Provisioning → Get Everything → Initial backup
- Factory reset path: Detect backup > 1 min → Restore

### Nokia Beacon TR-098 → TR-181 Migration Planning

Parameter mapping challenges:
| Aspect | TR-098 | TR-181 |
|--------|--------|--------|
| Root | `InternetGatewayDevice.` | `Device.` |
| WiFi | `LANDevice.1.WLANConfiguration.` | `WiFi.Radio./WiFi.SSID.` |
| NAT | `WANIPConnection.1.PortMapping.` | `NAT.PortMapping.` |

---

## November 25, 2025

### Authentication & User Management

- Laravel Breeze with Blade templates
- Three-tier roles: Admin, Support, User
- Password enforcement on first login
- API authentication via Sanctum

### Port Forwarding Testing Status

| Device Model | Add | Delete | Notes |
|--------------|-----|--------|-------|
| SR516ac | ✅ | ✅ | ~8 seconds for add |
| Others | Pending | Pending | Testing in progress |

---

*For current system status and active configuration, see [CLAUDE.md](../CLAUDE.md)*
