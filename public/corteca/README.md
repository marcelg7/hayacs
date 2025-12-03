# Corteca Migration Pre-config Files

This directory contains pre-configuration files used during the Nokia Beacon G6 TR-098 to TR-181 (Corteca) migration.

## Files Required

1. **preconfig.xml** - The vendor configuration file that triggers the TR-181 conversion
   - This file must be obtained from Nokia/Alcatel-Lucent
   - It contains the settings that instruct the device to switch to TR-181 mode

## How Migration Works

1. **Version Check**: Verify firmware >= 24.02a
2. **Datamodel Check**: Confirm device is on TR-098
3. **Transition Backup**: Full backup of current settings
4. **Load Pre-config**: Download preconfig.xml which triggers conversion and reboot
5. **Restore Settings**: After reboot, restore converted settings to TR-181 format

## Parameter Mapping

The restore step automatically converts TR-098 parameters to TR-181 format:

| TR-098 Path | TR-181 Path |
|-------------|-------------|
| InternetGatewayDevice.LANDevice.1.WLANConfiguration.* | Device.WiFi.* |
| InternetGatewayDevice.Time.* | Device.Time.* |
| InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.* | Device.DHCPv4.Server.* |
| InternetGatewayDevice.WANDevice.1.*.PortMapping.* | Device.NAT.PortMapping.* |

## Notes

- The pre-config file URL is configured in the "Corteca Step 4: Load Pre-config" workflow
- Default URL: https://hayacs.hay.net/corteca/preconfig.xml
- Ensure the file is accessible via HTTPS before activating the migration workflow
