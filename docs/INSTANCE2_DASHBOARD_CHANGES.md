# Claude Code Instance 2 - Dashboard.blade.php Changes

**Date**: December 17, 2025
**File**: `/var/www/hayacs/resources/views/device-tabs/dashboard.blade.php`

## Summary of Changes

Instance 2 added two new sections to the dashboard:
1. **LAN Configuration Editing** - Edit button and modal for LAN/DHCP settings
2. **Admin Credentials Section** - Display and reset customer admin password

---

## 1. LAN Section Enhancement (Lines ~518-744)

### What was added:
- **Edit button** in the LAN section header
- **Alpine.js data object** for LAN editing functionality
- **Edit modal** with form fields for LAN IP, DHCP Start, DHCP End
- **Client-side validation** for IP address format
- **API integration** to POST changes to `/api/devices/{id}/lan-config`
- **Auto-reboot** - After LAN changes save, a reboot task is automatically queued

### Code Location: Lines 518-744

The LAN section's `x-data` object (starting line 519):
```javascript
x-data="{
    showLanEditModal: false,
    lanConfig: {
        lan_ip: '...',
        subnet_mask: '...',
        dhcp_start: '...',
        dhcp_end: '...'
    },
    originalConfig: {},
    errors: {},
    saving: false,
    openEditModal() { ... },
    closeEditModal() { ... },
    validateIp(ip) { ... },
    validateForm() { ... },
    async saveLanConfig() { ... }
}"
```

### Key features:
- Edit button in section header (line ~629-634)
- Modal with LAN IP, DHCP Start, DHCP End inputs (lines ~675-743)
- Validation info box explaining DHCP must be in same subnet
- Task tracking message: "Updating LAN Configuration (reboot will follow)..." (line 614)

---

## 2. Admin Credentials Section (Lines 747-933)

### What was added:
- **New section** between LAN and Mesh Network sections
- **Yellow color theme** to distinguish from other sections
- **Username/Password display** with show/hide toggle
- **Copy to clipboard** button for password
- **Reset Password modal** with random or custom password option

### Code Location: Lines 747-933

#### PHP Logic for Parameter Selection (lines 747-768):
```php
@php
    // Determine admin credential parameters based on device type
    // User.1 = customer admin account, User.2 = support/superadmin (internal use only)
    if ($device->isNokia()) {
        if ($isDevice2) {
            // Nokia TR-181: User.1 = admin, User.2 = superadmin
            $adminUsernameParam = 'Device.Users.User.1.Username';
            $adminPasswordParam = 'Device.Users.User.1.Password';
        } else {
            // Nokia TR-098: User.1 = admin, User.2 = superadmin (same structure as Calix)
            // Note: X_Authentication.WebAccount is the superadmin - don't use for customer
            $adminUsernameParam = 'InternetGatewayDevice.User.1.Username';
            $adminPasswordParam = 'InternetGatewayDevice.User.1.Password';
        }
    } else {
        $adminUsernameParam = 'InternetGatewayDevice.User.1.Username';
        $adminPasswordParam = 'InternetGatewayDevice.User.1.Password';
    }
    $adminUsername = $getExactParam($adminUsernameParam);
    $adminPassword = $getExactParam($adminPasswordParam);
@endphp
```

#### Alpine.js Data Object (lines 769-818):
```javascript
x-data="{
    showPassword: false,
    showResetModal: false,
    customPassword: '',
    useCustomPassword: false,
    resetting: false,
    newPassword: null,
    adminPassword: '{{ $adminPassword }}',
    async resetPassword() { ... }
}"
```

### Key features:
- Section header with key icon and "Admin Credentials" title (yellow theme)
- Reset Password button in header
- Username row (defaults to "admin" if not found)
- Password row with:
  - Show/hide toggle (eye icon)
  - Copy to clipboard button
  - "Not available - run Get Everything" message if password not in database
- Reset Password Modal with:
  - Checkbox to use custom password
  - Custom password input (6-32 chars)
  - Info box about random password generation
  - Green success box showing new password after reset

---

## API Routes Used

These routes are called by the dashboard:

```php
// LAN Configuration
Route::get('/devices/{id}/lan-config', [DeviceController::class, 'getLanConfig']);
Route::post('/devices/{id}/lan-config', [DeviceController::class, 'updateLanConfig']);

// Admin Credentials
Route::get('/devices/{id}/admin-credentials', [DeviceController::class, 'getAdminCredentials']);
Route::post('/devices/{id}/admin-credentials/reset', [DeviceController::class, 'resetAdminPassword']);
```

---

## Important Notes

1. **User.1 vs User.2**:
   - User.1 = Customer admin account (shown to customers)
   - User.2 = Support/superadmin (internal use only, NOT shown here)

2. **Nokia TR-098 correction**: Initially used `X_Authentication.WebAccount` which is superadmin - corrected to use `User.1` like Calix devices

3. **LAN Reboot**: The `updateLanConfig` method in DeviceController now creates a follow-up reboot task with `wait_for_next_session = true` because LAN changes require a reboot to take effect

4. **Section Position**: Admin Credentials section is placed between LAN section and Mesh Network section (line 747, before line 935 Mesh section)

---

## Files Also Modified by Instance 2

1. **`/var/www/hayacs/app/Http/Controllers/Api/DeviceController.php`**
   - Added `getLanConfig()` method
   - Added `updateLanConfig()` method with validation and reboot task creation
   - Added `validateLanConfiguration()` private method
   - Added `subnetMaskToCidr()` private method
   - Added `getAdminCredentials()` method
   - Added `resetAdminPassword()` method
   - Added `generateRandomPassword()` private method

2. **`/var/www/hayacs/routes/api.php`**
   - Added LAN config routes (lines 72-74)
   - Added Admin credentials routes (lines 76-78)
