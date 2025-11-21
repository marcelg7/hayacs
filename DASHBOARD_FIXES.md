# Dashboard Issues - Testing Findings

## Issues Found

### 1. Connect Now Button - Returns JSON
**Status:** Needs Fix
**Location:** Line 145 in device.blade.php
**Issue:** Plain form POST that navigates to JSON response
**Solution:** Convert to AJAX with proper user feedback

### 2. Tasks 1014 & 1015 Failed - Remote GUI
**Status:** Expected Behavior (SmartRG limitation)
**Details:**
- Task 1014: Get Remote GUI parameters - Timed out
- Task 1015: Set Remote GUI parameters - Timed out
- **Root Cause:** SmartRG devices don't support these specific InternetGatewayDevice.User/UserInterface.RemoteAccess parameters
- **Solution:** Disable Remote GUI button for SmartRG devices

### 3. Ping Test (Task 1017) - Invalid Min Response Time
**Status:** Device firmware bug
**Details:**
- MinimumResponseTime: 4294967 ms (invalid - near max uint32)
- MaximumResponseTime: 0 ms (invalid)
- AverageResponseTime: 0 ms (invalid)
- SuccessCount: 4 (correct - pings succeeded!)
- **Root Cause:** SmartRG firmware returns garbage timing data even though pings succeed
- **Solution:** Display warning when response times look invalid, or filter/hide invalid values

### 4. Traceroute (Task 1018) - Failed/Timeout
**Status:** SmartRG doesn't support
**Details:** Task timed out after 2.47 minutes
- **Root Cause:** SmartRG devices may not support TR-069 Traceroute diagnostics
- **Solution:** Test on Calix device to confirm it works, consider disabling for SmartRG

### 5. Reboot Uptime Not Auto-Updating
**Status:** Needs Fix
**Issue:** After reboot completes, uptime doesn't refresh until manual page refresh
- **Solution:** Queue a parameter refresh task for uptime after reboot completes
- Parameters to refresh:
  - InternetGatewayDevice.DeviceInfo.UpTime
  - InternetGatewayDevice.Time.X_000E50_NTPStatus (optional)

### 6. SmartRG Connection Requests
**Question:** Are they using connection requests or waiting for periodic informs?
- Need to check if connection_request_url is set for SmartRG devices
- Check if HTTP requests to SmartRG connection URLs succeed or fail

### 7. Connected Devices - Signal/Rate Not Loading
**Status:** Needs Investigation
**Issue:** Signal and Rate columns empty for SmartRG devices (works in USS)
- Need to identify which TR-069 parameters USS uses for this
- SmartRG likely uses different parameter paths than Calix
- Common parameters to check:
  - InternetGatewayDevice.LANDevice.1.WLANConfiguration.*.AssociatedDevice.*.SignalStrength
  - InternetGatewayDevice.LANDevice.1.WLANConfiguration.*.AssociatedDevice.*.LastDataTransmitRate

### 8. GUI Spacing Issue
**Status:** Easy Fix
**Issue:** No space between "Connected Devices" and "Recent Tasks" sections
- Other sections have spacing (likely using mt-6 or space-y-6)
- **Solution:** Add mt-6 class to Recent Tasks section div

## Priority Order
1. ✅ Fix Connect Now button (AJAX) - COMPLETED
2. ✅ Fix spacing issue - COMPLETED
3. ✅ Add reboot auto-refresh - COMPLETED (queues uptime refresh after reboot)
4. ✅ Disable Remote GUI for SmartRG - COMPLETED
5. ✅ Fix Connected Devices signal/rate parameters - COMPLETED
6. 🔍 Test connection requests on SmartRG - NEXT
7. 🤔 Consider handling invalid ping response times gracefully
8. 🤔 Consider disabling Traceroute for SmartRG

## Implementation Details

### 3. Reboot Auto-Refresh (COMPLETED)
- **Location**: CwmpController.php lines 664-683
- **Solution**: Modified handleRebootResponse() to queue a get_params task after reboot completes
- **Parameters refreshed**:
  - Device.DeviceInfo.UpTime (TR-181)
  - InternetGatewayDevice.DeviceInfo.UpTime (TR-098)
- **Behavior**: Task is automatically queued and will execute on next device check-in

### 4. Remote GUI Disabled for SmartRG (COMPLETED)
- **Location**: device.blade.php lines 498-550
- **Solution**: Added conditional logic to disable button for SmartRG devices
- **Visual indicators**:
  - Button opacity 50%, cursor not-allowed
  - Red prohibition icon displayed
  - Tooltip: "Not supported for SmartRG devices"
- **Reason**: SmartRG devices don't support InternetGatewayDevice.User/UserInterface.RemoteAccess parameters

### 5. Connected Devices Signal/Rate Parameters (COMPLETED)
- **Location**: device.blade.php lines 1138-1185 and 2196-2250
- **Problem**: SmartRG devices store WiFi signal and rate data differently than Calix devices
- **Root Cause**: SmartRG uses vendor-specific extensions in Hosts.Host table instead of AssociatedDevice parameters
- **Solution**: Added fallback logic to check SmartRG-specific parameters:
  - Signal: Falls back to `X_CLEARACCESS_COM_WlanRssi` from Host table (values: -54, -44, -58 dBm)
  - Rate: Falls back to `X_CLEARACCESS_COM_WlanTxRate` and `X_CLEARACCESS_COM_WlanRxRate` from Host table (values in kbps: 78000, 58500, 104000)
- **Implementation**: Code first checks standard AssociatedDevice parameters, then falls back to SmartRG vendor extensions
- **Testing**: Verified SmartRG 505n has working signal and rate data in Host table parameters
