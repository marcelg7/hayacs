# Nokia Beacon G6: TR-098 to TR-181 Migration Plan

**Document Version:** 1.2
**Created:** November 27, 2025
**Last Updated:** November 27, 2025
**Status:** Planning Phase

---

## Overview

This document outlines the migration plan for Nokia Beacon G6 devices from TR-098 (`InternetGatewayDevice.*`) to TR-181 (`Device.*`) data model. This migration is required to enable TR-369 USP communication with Nokia Corteca ACS while maintaining TR-069 communication with Hay ACS.

**Scale:** ~3,500 Beacon G6 devices
**Risk:** Loss of customer WiFi settings if not handled correctly
**Solution:** Nokia's hidden `X_ALU-COM_ConfigMigration` parameter preserves WiFi middleware database

---

## The Problem

When a Beacon G6 switches from TR-098 to TR-181 data model:
- The firmware does NOT automatically migrate WiFi configuration
- WiFi radios come up with **factory-default SSIDs and passwords**
- All mesh satellites (Beacon 2/3/3.1) lose their wireless backhaul connection
- Customers must manually re-enter WiFi settings and re-pair all satellites

**Without proper migration:** 100% of devices lose WiFi settings → major customer impact

---

## The Solution

Nokia provides a hidden/undocumented parameter that preserves the WiFi middleware database during migration:

```
InternetGatewayDevice.DeviceInfo.X_ALU-COM_ConfigMigration = 1
```

When set before the migration reboot:
- **99.8% success rate** (only units with corrupted NVRAM fail)
- WiFi SSID, password, band-steering, parental controls all preserved
- Mesh satellite connections maintained (no re-pairing needed)
- Customers experience only a normal 2-minute reboot

---

## Migration Prerequisites

A device is **eligible for migration** when ALL of the following are true:

| Requirement | Check |
|-------------|-------|
| **Firmware Version** | Must be running `3FE49996IJMK14` (or approved TR-181-capable version) |
| **Current Data Model** | Must be TR-098 (`InternetGatewayDevice.*` root) |
| **Connected to Hay ACS** | Device must be actively managed |
| **Initial On-boarding Complete** | Firmware upgrade (if needed) already done |

### Firmware Requirement

The device CANNOT be migrated unless it is already running TR-181-capable firmware. This firmware is applied during **initial on-boarding**, not during migration.

**On-boarding Flow (separate from migration):**
1. Device connects to Hay ACS
2. Auto-backup created
3. Firmware version checked against DeviceType target
4. If outdated → firmware upgrade to `3FE49996IJMK14`
5. Device reboots and reconnects
6. Device now eligible for future TR-181 migration

---

## Migration Flow

### Phase 1: Pre-Migration (Safety Net)

**Step 1: Verify Eligibility**
```
Check firmware version = 3FE49996IJMK14 (or approved)
Check data model root = InternetGatewayDevice.* (TR-098)
```

**Step 2: Harvest ALL Parameters**
Full "Get Everything" backup as safety net:
- All `InternetGatewayDevice.*` parameters
- Specifically capture WiFi configuration paths:
  ```
  InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.   (2.4 GHz primary)
  InternetGatewayDevice.LANDevice.1.WLANConfiguration.5.   (5 GHz primary)
  InternetGatewayDevice.LANDevice.1.WLANConfiguration.9.   (6 GHz if present)
  InternetGatewayDevice.LANDevice.1.WLANConfiguration.2/6/10 (guest/IoT)
  InternetGatewayDevice.X_ALU-COM_Wifi.*
  InternetGatewayDevice.X_ALU-COM_BandSteering.*
  InternetGatewayDevice.X_ALU-COM_WifiSchedule.*
  InternetGatewayDevice.X_ALU-COM_NokiaParentalControl.*
  ```

### Phase 2: Migration Execution

**Step 3: Set ConfigMigration Flag**
```xml
<SetParameterValues>
  <ParameterList>
    <ParameterValueStruct>
      <Name>InternetGatewayDevice.DeviceInfo.X_ALU-COM_ConfigMigration</Name>
      <Value xsi:type="xsd:string">1</Value>
    </ParameterValueStruct>
  </ParameterList>
</SetParameterValues>
```

**Step 4: Push Pre-Config File**
Download the TR-181 conversion pre-config file via TR-069 Download RPC:
- FileType: `3 Vendor Configuration File`
- Use Hay ACS customized file: `beacon-g6-pre-config-hayacs-tr181.xml`
- This triggers the data model switch on next reboot

**Pre-Config File Details:**
The pre-config file contains a critical parameter that triggers the TR-181 switch:
```xml
<X_ASB_COM_TR181Enabled v="true" dburi="InternetGatewayDevice.ManagementServer.X_ASB_COM_TR181Enabled"/>
```

**WARNING:** The pre-config file also contains default WiFi settings (SSID, passwords, etc.). Without `ConfigMigration = 1` set BEFORE applying this file, these defaults will OVERWRITE the customer's existing WiFi configuration!

**Step 5: Device Reboots**
- Device applies pre-config
- Middleware detects `ConfigMigration = 1`
- WiFi middleware database preserved
- Device boots into TR-181 mode

### Phase 3: Post-Migration

**Step 6: Device Reconnects**
- Device informs with `Device.*` root (TR-181)
- May include `1 BOOT` or `0 BOOTSTRAP` event
- Verify data model changed successfully

**Step 7: Verify WiFi Preserved**
- Check `Device.WiFi.SSID.*` parameters
- Confirm SSID matches pre-migration values
- Verify mesh satellites reconnected (if applicable)

---

## Fallback Procedure

If WiFi settings are NOT preserved (ConfigMigration failed):

1. Device reconnects with default SSIDs (`NokiaWiFi_XXXX`)
2. System detects WiFi mismatch from harvested backup
3. Immediately push TR-181 WiFi parameters:
   ```
   Device.WiFi.SSID.1.SSID = [harvested value]
   Device.WiFi.AccessPoint.1.Security.KeyPassphrase = [harvested value]
   ... etc
   ```
4. WiFi restored within 15-25 seconds of reconnection

**TR-098 to TR-181 Parameter Mapping:**
| TR-098 Path | TR-181 Path |
|-------------|-------------|
| `InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID` | `Device.WiFi.SSID.1.SSID` |
| `InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase` | `Device.WiFi.AccessPoint.1.Security.KeyPassphrase` |

*(Full mapping table to be completed)*

---

## Mesh Satellite Behavior

When migrating a root Beacon G6 with connected satellites (Beacon 2/3/3.1):

**With ConfigMigration = 1:**
- Root reboots (~90-120 seconds outage)
- Satellites automatically re-establish wireless backhaul
- No WPS re-pairing needed
- Mesh topology preserved

**Without ConfigMigration:**
- Backhaul credentials reset
- All satellites lose connection
- Satellites broadcast default `NokiaWiFi_Setup_XXXX`
- Customers must re-onboard every satellite manually

**Note:** Satellites do NOT need firmware upgrades. They remain on their current firmware and reconnect automatically when the root's backhaul credentials are preserved.

---

## Implementation Checklist

### ACS Development Tasks

- [ ] Create migration eligibility check function
  - Verify firmware version
  - Verify TR-098 data model
  - Return eligibility status

- [ ] Create "Harvest All Parameters" task for pre-migration backup
  - Full GetParameterValues on `InternetGatewayDevice.`
  - Store with migration flag in database

- [ ] Create "Set ConfigMigration" task type
  - SetParameterValues for `X_ALU-COM_ConfigMigration = 1`

- [ ] Create "Push Pre-Config" task type
  - Download RPC with TR-181 conversion file
  - FileType: `3 Vendor Configuration File`

- [ ] Create migration workflow orchestrator
  - Sequence: Eligibility → Harvest → ConfigMigration → Pre-Config
  - Handle task dependencies

- [ ] Create post-migration verification
  - Detect data model change
  - Verify WiFi preservation
  - Trigger fallback if needed

- [ ] Create TR-098 to TR-181 parameter mapping for fallback

- [ ] Create migration status dashboard
  - Eligible devices count
  - Migration in progress
  - Completed migrations
  - Failed migrations

### Testing Tasks

- [ ] Identify test devices (TR-098 on correct firmware)
- [ ] Verify `X_ALU-COM_ConfigMigration` parameter exists
- [ ] Test migration on single device
- [ ] Test with mesh satellites attached
- [ ] Test fallback procedure
- [ ] Document observed behavior

---

## Risk Mitigation

| Risk | Mitigation |
|------|------------|
| ConfigMigration parameter doesn't exist | Check with GetParameterNames before migration; use fallback |
| Corrupted NVRAM | Fallback to TR-181 parameter push from backup |
| Pre-config file fails | Device stays on TR-098; can retry |
| Network interruption during migration | Device completes locally; reconnects when network available |
| Mass migration overloads ACS | Batch migrations with rate limiting |

---

## Success Criteria

- [ ] Device transitions from TR-098 to TR-181
- [ ] WiFi SSID unchanged
- [ ] WiFi password unchanged
- [ ] Band steering settings preserved
- [ ] Parental controls preserved
- [ ] Mesh satellites reconnect without re-pairing
- [ ] Device communicates via TR-069 to Hay ACS
- [ ] Device ready for TR-369 USP to Corteca

---

## Device ID Change Handling

### The Issue
When a Beacon G6 migrates from TR-098 to TR-181, the pre-config file may change the device's OUI (ManufacturerOUI parameter). This causes the device to appear with a new device ID since the ID format is `OUI-ProductClass-SerialNumber`.

**Example:**
- Before migration: `80AB4D-Beacon G6-ALCLFD0A7F1E` (TR-098)
- After migration: `0C7C28-Beacon G6-ALCLFD0A7F1E` (TR-181)

### Solution

**1. Pre-Config File Fix:**
The Hay ACS pre-config file has been modified to remove the hardcoded ManufacturerOUI, allowing devices to keep their original OUI.

**2. Automatic Detection:**
The system includes logic to detect when a device reconnects with a different OUI but same serial number:
- `checkForMigratedDevice()` - Detects if a TR-181 device has a TR-098 predecessor
- `mergeDeviceRecords()` - Migrates backups and settings from old to new device record

**3. Data Preserved:**
When a device ID change is detected, the following data is automatically migrated:
- Configuration backups (with `migrated_from_device_id` metadata)
- Subscriber association
- Connection request credentials
- Device tags (with migration tracking)

**4. Old Device Record:**
The old device record is:
- Tagged with `tr181_migrated_to_[new_id]`
- Marked as offline
- Preserved for historical reference

---

## Pre-Config File: Hay ACS Customization

The pre-config file has been customized for Hay ACS deployment. The customized file is located at:
```
docs/beacon-g6-pre-config-hayacs-tr181.xml
```

### Key Parameters Changed from Nokia Default

| Parameter | Nokia Default | Hay ACS Custom |
|-----------|---------------|----------------|
| `ManagementServer.URL` | `https://acs.nokia.net:7754` | `http://hayacs.hay.net/cwmp` |
| `ManagementServer.Username` | `admin` | `acs-user-d30UJ` |
| `ManagementServer.Password` | `admin` | `qEKOH550RhORJD3` |
| `ConnectionRequestUsername` | `itms` | `admin` |
| `ConnectionRequestPassword` | `itms` | `admin` |
| `PeriodicInformInterval` | `3600` | `600` |
| `X_ALU-COM_EnableServerCertValidation` | `true` | `false` |
| `X_ALU_COM_XMPP_Enable` | `true` | `false` |
| `X_ASB-COM_Option43Enable` | `OPTION43_ON` | `OPTION43_OFF` |
| `Time.NTPServer1` | `time.nist.gov` | `ntp.hay.net` |
| `Time.LocalTimeZoneName` | `Pacific Time, Tijuana` | `EST+5EDT,M3.2.0/2,M11.1.0/2` |
| `TrustedNetwork.1.SourceIPRangeStart` | (empty) | `163.182.253.90` |
| `TrustedNetwork.1.SourceIPRangeEnd` | (empty) | `163.182.253.90` |

### Critical Migration Parameters

| Parameter | Value | Purpose |
|-----------|-------|---------|
| `X_ASB_COM_TR181Enabled` | `true` | **Triggers TR-181 data model switch** |
| `X_ALU-COM_ConfigMigration` | `1` | **Preserves WiFi middleware database** (must be set BEFORE pre-config) |

### Pre-Config File Structure

The Nokia pre-config file uses a proprietary XML format with these sections:
- `<GENERIC.>` - Generic WAN and system settings
- `<HAYACS.>` (renamed from EGEB) - Operator-specific configuration
  - `<Basic.>` - OUI, provisioning code
  - `<ManagementServer.>` - ACS URL, credentials, TR-181 switch parameter
  - `<WANConnectionDevice_X.>` - WAN interface configuration
  - `<WLAN_X.>` - WiFi configuration (defaults - will be overwritten without ConfigMigration)
  - `<Time.>` - NTP and timezone settings

### WiFi Default Values in Pre-Config (Will Be Ignored with ConfigMigration=1)

The pre-config file contains default WiFi settings that would apply on a factory reset:
- SSID format: `NOKIA-%4M` (MAC-based)
- Password: Generated (`%10p` token)
- Security: WPA2-PSK (AES)
- Band steering: Enabled
- 2.4 GHz, 5 GHz radios: Enabled
- Guest networks: Disabled by default

**These defaults are IGNORED when `X_ALU-COM_ConfigMigration = 1` is set before applying the pre-config file.**

---

## References

- Grok brainstorm document: `docs/beacon-gg-098-2-181-migration-for-369-enable-plan-to-not-lose-wifi-password-and-settings.txt`
- Nokia pre-config file (original): `docs/beacon-g6-pre-config-file-to-switch-to-tr-181`
- Hay ACS customized pre-config: `docs/beacon-g6-pre-config-hayacs-tr181.xml`
- TR-069 Amendment 6 (Download RPC FileTypes)
- Nokia Beacon G6 firmware release notes (for ConfigMigration availability)

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025-11-27 | Claude/Marcel | Initial draft |
| 1.1 | 2025-11-27 | Claude/Marcel | Added pre-config file analysis and Hay ACS customization details |
| 1.2 | 2025-11-27 | Claude/Marcel | Added device ID change handling for OUI changes; removed hardcoded OUI from pre-config |
