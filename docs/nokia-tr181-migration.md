# Nokia Beacon G6 TR-098 to TR-181 Migration

**Last Updated**: December 2, 2025
**Status**: Ready for testing - new simpler preconfig file received (PREEGEBOPR)

## Overview

Nokia Beacon G6 devices can operate in two data model modes:
- **TR-098 (EGEA)**: Uses `InternetGatewayDevice.` prefix, OUI `80AB4D`
- **TR-181 (EGEB)**: Uses `Device.` prefix, OUI `0C7C28`

The migration process converts devices from TR-098 to TR-181 mode using a vendor-specific preconfig file.

## Test Device

| Property | TR-098 (Before) | TR-181 (After) |
|----------|-----------------|----------------|
| Device ID | `80AB4D-Beacon G6-ALCLFD0FC633` | `0C7C28-Beacon G6-ALCLFD0FC633` |
| OUI | `80AB4D` | `0C7C28` |
| Data Model | InternetGatewayDevice. | Device. |
| Parameters | 4,678 | 4,623 |
| ProvisioningCode | EGEA | EGEB |

## Preconfig File Format

### USTAR TAR Header (512 bytes)
The Nokia preconfig file requires a USTAR TAR-style header:

| Offset | Length | Field | Value |
|--------|--------|-------|-------|
| 0 | 100 | Filename | `PREEGEB101` (null-padded) |
| 100 | 8 | File mode | `0000666\0` |
| 108 | 8 | Owner UID | `0000000\0` |
| 116 | 8 | Group GID | `0000000\0` |
| 124 | 12 | File size | Octal (e.g., `00000104110\0`) |
| 136 | 12 | Mod time | Octal Unix timestamp |
| 148 | 8 | Checksum | Octal with space terminator |
| 156 | 1 | Link flag | `0` |
| 257 | 6 | Magic | `ustar\0` or `ustar ` |

### XML Content Structure
```xml
<!--file_type=pre_update_remote-->
<PreConfigurationRemote. n="PreConfigurationRemote" t="staticObject" ver="0.0.3" new_opid="EGEB">
    <GENERIC. n="GENERIC" a="static">
        <!-- Generic settings -->
    </GENERIC.>
    <EGEB. n="EGEB" a="static">
        <Basic. n="basic_setting" a="static">
            <oui t="string" ml="8" v="D0542D" fri="" dburi="*DEL*"/>
            <ManufacturerOUI t="string" ml="12" v="0C7C28" dburi="InternetGatewayDevice.DeviceInfo.ManufacturerOUI"/>
            <ProvisioningCode t="string" ml="12" v="EGEB" dburi="InternetGatewayDevice.DeviceInfo.ProvisioningCode"/>
        </Basic.>
        <!-- Additional configuration sections -->
    </EGEB.>
</PreConfigurationRemote.>
```

### Key Configuration Elements
- `new_opid="EGEB"` - Operator ID that triggers TR-181 mode
- `ManufacturerOUI` set to `0C7C28` - New OUI for TR-181
- `ProvisioningCode` set to `EGEB` - Identifies TR-181 mode

## Testing Results

### December 1, 2025

#### Preconfig Download Testing

| Test | URL | Result |
|------|-----|--------|
| Local hayacs copy | `https://hayacs.hay.net/device-config/migration/...` | Failed - "file corrupted or unusable" (9018) |
| Original Nokia copy | `https://hayacs.hay.net/device-config/migration/original-nokia-preconfig.xml` | Failed - same error |
| hay.net URL | `https://hay.net/PREEGEB101` | **SUCCESS** |

#### Key Finding
Even an identical copy of the working file fails when served from hayacs.hay.net. The issue is **not the file content** but how the file is served. Possible causes:
1. HTTP headers differ between hay.net and hayacs.hay.net
2. File serving method (static vs Laravel)
3. Content-Type or other header differences

#### Successful TR-181 Conversion
Using the hay.net URL, the device successfully:
1. Downloaded the preconfig file
2. Applied the configuration
3. Rebooted with new OUI `0C7C28`
4. Connected back reporting TR-181 data model (`Device.` prefix)
5. Collected 4,623 parameters

## New Simpler Preconfig (December 2, 2025)

### PREEGEBOPR - Simpler Migration File

Sales Engineer provided a much simpler preconfig file that **only changes OperatorID** to trigger TR-181 mode.

**Key Benefit**: Device keeps the same OUI (80AB4D) - no device ID change!

**File Location**: `https://hay.net/PREEGEBOPR`

**File Content** (106 bytes):
```xml
<OpertaorObject version="1.0">
<OperatorID="EGEB">
</OpertaorObject>
```

**What this means**:
- No OUI change (80AB4D stays the same)
- No duplicate device issues
- Device ID remains: `80AB4D-Beacon G6-{serial}`
- Workflow continuity maintained
- Historical data preserved
- Migration is much simpler

### Previous Solution (deprecated)
The old preconfig file (beacon-g6-pre-config-hayacs-tr181.xml) changed both:
- `ManufacturerOUI`: `80AB4D` → `0C7C28`
- `ProvisioningCode`: `EGEA` → `EGEB`

This caused device ID changes and required complex device record merging.

## File Locations

| File | Path | Notes |
|------|------|-------|
| **PREEGEBOPR (Current)** | `https://hay.net/PREEGEBOPR` | **New simpler file - USE THIS** |
| Local copy of PREEGEBOPR | `/var/www/hayacs/public/device-config/migration/PREEGEBOPR` | Downloaded copy |
| Old USS preconfig | `https://hay.net/PREEGEB101` | Used by USS (changes OUI) |
| Old Hay ACS XML | `/var/www/hayacs/docs/beacon-g6-pre-config-hayacs-tr181.xml` | Deprecated |

## DHCP Option 43 Issue

During testing, the device was redirected back to USS via DHCP Option 43 after the preconfig download was sent. The device's DHCP lease renewal received the USS ACS URL, overriding the connection to Hay ACS.

**Workaround**: Manually push device back to Hay ACS after conversion.

**Long-term**: Update DHCP Option 43 configuration to point to Hay ACS before migration.

## Next Steps

1. **Test PREEGEBOPR** on a TR-098 device - verify device keeps same OUI after migration
2. **Update DHCP Option 43** for production migration (prevent redirect to USS)
3. **Verify WiFi/settings preservation** after migration with new simpler file

## Related Files

- `app/Services/CwmpService.php` - TR-069 handling
- `app/Http/Controllers/CwmpController.php` - Download task handling
- `docs/device-groups-schema-design.md` - Workflow documentation
