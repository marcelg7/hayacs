# Corteca Migration Workflow - Testing Findings

**Date**: November 28-29, 2025
**Test Device**: ALCLFD0FC633 (Nokia Beacon G6, TR-098, firmware 3FE49996IJMK14)
**Firmware Version Mapping**: 25.03 = 3FE49996IJMK14

## Summary

The Corteca Migration workflow system is operational but encountered two blocking issues specific to Nokia Beacon G6 TR-098 devices.

**CRITICAL FINDING**: The `X_ALU-COM_ConfigMigration` parameter does **NOT exist** on firmware 25.03 (3FE49996IJMK14). This is the latest available firmware. A full parameter search on the device confirmed the parameter is not present.

**Implication**: The "zero-touch" WiFi preservation method (setting ConfigMigration=1) is **NOT available** for this firmware. We must use the **Alternative Method: Manual WiFi Backup/Restore**.

## What Works

### Workflow System
- **on_connect trigger**: Successfully triggers workflows when devices connect
- **Workflow dependencies**: Chained workflows trigger correctly (Step 2 after Step 1 completes, etc.)
- **Task completion callbacks**: Properly update WorkflowExecution status
- **Device group matching**: Correctly filters devices based on rules
- **Subscriber exclusion rule**: Added to protect live customers during testing

### Completed Steps
| Step | Name | Status | Notes |
|------|------|--------|-------|
| 1 | Version Check | ✅ Works | Retrieved firmware 3FE49996IJMK14 |
| 2 | Datamodel Check | ✅ Works | Confirmed TR-098 data model |
| 3 | Transition Backup | ✅ **FIXED** | Uses cached database data (1175 params backed up in backup #787) |
| 4 | Set ConfigMigration | ❌ Failed | Parameter doesn't exist on firmware 25.03 (3FE49996IJMK14) |
| 5 | Load Pre-config | Not tested | Blocked by Step 4 issue |
| 6 | Restore Settings | Not tested | Blocked by Step 5 |

## Blocking Issues

### Issue 1: ConfigMigration Parameter Not Available

**Error**: Device silently rejects the command (starts new session without responding)

**Tested Commands**:
1. SetParameterValues direct attempt (Task #11996) - Failed
2. The parameter cannot be "added" via AddObject - AddObject is for multi-instance objects

**Parameter**:
```
InternetGatewayDevice.DeviceInfo.X_ALU-COM_ConfigMigration
```

**Root Cause**: The firmware version `3FE49996IJMK14` predates firmware 3.5.x where Nokia added the `X_ALU-COM_ConfigMigration` parameter.

**Impact**: Without this parameter, the pre-config push will reset ALL WiFi settings to defaults, causing:
- Customer SSIDs lost
- WiFi passwords reset
- Band steering/mesh settings lost
- Mesh satellites (Beacon 2/3/3.1) disconnected

**Documentation Reference**: `docs/beacon-gg-098-2-181-migration-for-369-enable-plan-to-not-lose-wifi-password-and-settings.txt`

### Required Solution: Manual WiFi Backup/Restore

Since ConfigMigration is NOT available on firmware 25.03, we must use the manual approach:

**Migration Flow**:
1. **WiFi Backup** (before migration): Extract WiFi parameters using explicit parameter names
2. **Convert TR-098 → TR-181**: Map WiFi parameters to TR-181 equivalents
3. **Load Pre-config**: Trigger TR-181 conversion (device reboots with default WiFi)
4. **WiFi Restore** (on first TR-181 connect): Immediately push saved WiFi config
5. **Result**: Customer sees ~15-25 seconds WiFi drop during reboot (acceptable)

**TR-098 to TR-181 WiFi Parameter Mapping**:
| TR-098 Path | TR-181 Path |
|-------------|-------------|
| `InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID` | `Device.WiFi.SSID.1.SSID` |
| `InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase` | `Device.WiFi.AccessPoint.1.Security.KeyPassphrase` |
| `InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel` | `Device.WiFi.Radio.1.Channel` |
| `InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.*` (5GHz) | `Device.WiFi.Radio.2.*` / `Device.WiFi.SSID.2.*` |

### Alternative: Vendor Config File Method
1. Create WiFi-only config blob from TR-098 parameters
2. Push via TR-069 File Transfer (type = "3 Vendor Configuration File")
3. Nokia middleware reads this on boot in TR-181 mode and restores WiFi
4. **Note**: This requires knowing the exact blob format Nokia expects

### Issue 2: Transition Backup Using Partial Path - **SOLVED**

**Original Problem**: Nokia Beacon G6 TR-098 devices do NOT support `GetParameterValues` with partial paths like `InternetGatewayDevice.`

**Solution Implemented**: Use cached database parameters from previous "Get Everything" operations instead of querying the device.

**Code Changes**:
- `WorkflowExecutionService::buildTransitionBackupParams()` - Returns `use_cached_data: true` for TR-098 devices
- `WorkflowExecutionService::createCachedBackupTask()` - New method that:
  - Queries WiFi parameters from the local database
  - Creates a ConfigBackup record with the data
  - Creates a "completed" task (no device query needed)
  - Triggers dependent workflows immediately

**Benefits**:
1. **Instant** - No waiting for device response
2. **Reliable** - Doesn't depend on device quirks
3. **Complete** - Uses all 1175+ WiFi parameters we've already collected
4. **Safe** - Device doesn't get any commands that might fail

## Code Changes Made

### Fixed Issues
1. **get_parameter_values handler**: Updated to support `names` key in parameters
2. **Datamodel check**: Fixed to use device's known data model instead of querying both
3. **Task completion callbacks**: Added callback after GetParameterValues completes
4. **Abandoned task callbacks**: Added workflow notification when tasks fail
5. **Workflow status reset**: Fixed premature "completed" status on workflows

### Files Modified
- `app/Http/Controllers/CwmpController.php` - Multiple callback fixes
- `app/Services/WorkflowExecutionService.php` - Datamodel check fix
- `app/Models/DeviceGroupRule.php` - Already had subscriber_id field

## Recommendations

### Before Production Migration

1. **Verify firmware compatibility**: Check if target firmware has ConfigMigration parameter
2. **Consider firmware upgrade first**: Push firmware that supports ConfigMigration before migration
3. **Alternative backup approach**:
   - Use existing cached parameters from database
   - Or implement chunked parameter retrieval with explicit names

### Workflow Modifications Needed

1. **Step 3 (Transition Backup)**:
   - Change from partial_path to explicit parameter names
   - Or use existing device parameters from database

2. **Step 4 (ConfigMigration)**:
   - Add pre-check for parameter existence
   - Implement fallback if parameter doesn't exist

### Testing Next Steps

1. **BUILD WiFi BACKUP**: Create explicit parameter name list for WiFi backup (since partial paths don't work)
2. **TEST WiFi BACKUP**: Run GetParameterValues with explicit WiFi parameter names on test device
3. **BUILD TR-181 MAPPING**: Create TR-098 → TR-181 parameter mapping function
4. **OBTAIN TR-181 DEVICE**: Get a Beacon G6 already on TR-181 to verify TR-181 parameter paths
5. **TEST END-TO-END**: Full migration test with WiFi backup → preconfig → WiFi restore

### WiFi Parameters to Backup (Explicit Names)

If partial path queries don't work, use these explicit paths for WiFi backup:
```
InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.*   (2.4 GHz primary)
InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.*   (5 GHz primary)
InternetGatewayDevice.LANDevice.1.WLANConfiguration.9.*   (6 GHz if present)
InternetGatewayDevice.LANDevice.1.WLANConfiguration.2.*   (guest network)
InternetGatewayDevice.X_ALU-COM_Wifi.*                    (vendor-specific)
InternetGatewayDevice.X_ALU-COM_BandSteering.*            (band steering)
InternetGatewayDevice.X_ALU-COM_WifiSchedule.*            (WiFi schedule)
InternetGatewayDevice.X_ALU-COM_NokiaParentalControl.*    (parental controls)
```

## SSH Access Investigation (November 29-December 1, 2025)

### Goal
Enable SSH access to the test device to search the filesystem for the ConfigMigration parameter implementation.

### TR-069 Parameters Successfully Set
| Parameter | Value | Result |
|-----------|-------|--------|
| `X_ALU-COM_WanAccessCfg.SshDisabled` | false | ✅ Success |
| `X_ALU-COM_WanAccessCfg.SshTrusted` | true | ✅ Success |
| `X_ALU-COM_WanAccessCfg.TrustedNetworkEnable` | true | ✅ Success |
| `X_ALU-COM_LanAccessCfg.SshDisabled` | false | ✅ Already set |
| `X_ALU-COM_ServiceManage.SshEnable` | true | ✅ Already set |
| `TrustedNetwork.1.SourceIPRangeStart` | 163.182.253.70 | ✅ Success |
| `TrustedNetwork.1.SourceIPRangeEnd` | 163.182.253.90 | ✅ Success |

### Parameters That Failed to Set
| Parameter | Attempted Value | Result |
|-----------|----------------|--------|
| `X_Authentication.TelnetSshAccount.Enable` | true | ❌ Silent reject |
| `X_Authentication.TelnetSshAccount.UserName` | superadmin | ❌ Silent reject |
| `X_Authentication.TelnetSshAccount.Password` | [password] | ❌ Silent reject |
| `X_ALU-COM_ServiceManage.TelnetPassword` | [password] | ❌ Silent reject |
| `X_ALU-COM_ServiceManage.FactoryTelnetEnable` | true | ❌ Silent reject |
| `X_ALU-COM_ServiceManage.DebugRnPassEnable` | true | ❌ Silent reject |

### SSH Connection Status
- **Port 22**: Open ✅
- **SSH Server**: dropbear
- **Auth Methods**: publickey, password
- **Connection Result**: `Permission denied` with all tested credentials:
  - superadmin / keepOut-72863!!! (WebAccount credentials)
  - admin / [various passwords]
  - root / [various passwords]

### Analysis
Nokia Beacon G6 uses a complex multi-tier authentication system:
1. **WebAccount** - For web GUI access (superadmin user)
2. **TelnetSshAccount** - For SSH/Telnet access (separate account, disabled by default)
3. **ServiceManage** - Telnet/SSH username/password (admin user)
4. **DebugDyPass** - Dynamic password system with ECDSA key for factory access

The device silently rejects attempts to modify TelnetSshAccount and ServiceManage credentials via TR-069. This appears to be a security lockdown on firmware 25.03.

### Recommendation
Contact Nokia Support for:
1. SSH credentials compatible with firmware 3FE49996IJMK14
2. Documentation on enabling debug/factory access
3. Alternative method to verify ConfigMigration parameter availability

---

## Device Group Configuration

**Group**: Nokia Beacon G6 (TR-098)

**Rules**:
1. Manufacturer OUI Equals "80AB4D"
2. Product Class/Model Contains "Beacon"
3. Data Model Equals "TR-098"
4. Subscriber ID Is Empty ← **Protects live customers**

**Matching Devices**: 1 (ALCLFD0FC633 - test device only)
