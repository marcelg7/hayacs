# Hay ACS Reports & Automation TODO

**Created**: December 3, 2025
**Goal**: Implement USS-equivalent reports with intelligent automation and guided remediation

---

## Phase 1: Problem Detection Reports (High Priority)

These reports identify issues that could benefit from automated solutions or guided remediation.

### 1. Offline Devices Report
**USS Equivalent**: Devices - Offline Devices (Table)

**What it shows**: Devices that haven't checked in within expected timeframe

**Automation Ideas**:
- [ ] Auto-send connection request to wake device
- [ ] Check if device IP is pingable (network issue vs device issue)
- [ ] Cross-reference with subscriber records - is service active?
- [ ] Check last known WAN IP - has it changed? (DHCP issue)
- [ ] One-click "Troubleshooting Wizard":
  1. Ping device's last known IP
  2. Check if subscriber has open trouble ticket
  3. Offer to schedule field tech visit
  4. Show device location on map (if GPS/address available)
- [ ] Alert thresholds: 1 hour (warning), 24 hours (critical), 7 days (presumed dead)
- [ ] Bulk action: "Wake All Offline Devices" (send connection requests)

---

### 2. 30+ Day Inactive Devices Report
**USS Equivalent**: Devices - 30 Day+ Inactive Devices (Table)

**What it shows**: Equipment that may be disconnected, returned, or stolen

**Automation Ideas**:
- [ ] Cross-reference with billing system - is customer still paying?
- [ ] Flag for equipment recovery if service cancelled
- [ ] Auto-generate "Equipment Return Request" letter/email
- [ ] Calculate potential revenue loss from unreturned equipment
- [ ] Integration with inventory system to mark as "In Field - Inactive"
- [ ] Bulk action: "Mark as Lost/Stolen" or "Schedule for Recovery"

---

### 3. Excessive Device Informs Report
**USS Equivalent**: Devices - Excessive Device Informs (Table)

**What it shows**: Devices checking in too frequently (>10x expected rate)

**Automation Ideas**:
- [ ] Auto-detect and alert on inform storms
- [ ] Show inform frequency graph per device
- [ ] One-click "Fix Inform Interval" - send SetParameterValues to correct
- [ ] Identify common causes:
  - Incorrect PeriodicInformInterval setting
  - Connection request loop
  - Firmware bug (flag by firmware version)
  - VALUE CHANGE events firing too often
- [ ] Auto-quarantine: Temporarily block device if causing server load issues
- [ ] Root cause analysis by firmware version (is this a known bug?)

---

### 4. Devices Without Subscriber Report
**USS Equivalent**: Devices - Devices without Subscriber (Table)

**What it shows**: Orphaned devices not linked to any customer

**Automation Ideas**:
- [ ] Auto-match by serial number to billing system
- [ ] Suggest potential matches based on:
  - MAC address patterns
  - IP address/location proximity
  - Similar customer names
- [ ] Bulk import tool to link devices to subscribers
- [ ] Flag as "Lab Device" or "Spare Inventory" options
- [ ] Dashboard widget: "X devices need subscriber linking"

---

### 5. Duplicate Serial Number Report
**USS Equivalent**: Devices - Duplicate Serial Number Device Records (Table)

**What it shows**: Data integrity issue - same serial appearing multiple times

**Automation Ideas**:
- [ ] Auto-detect on device registration
- [ ] Show comparison: Which record is newer? Which has more data?
- [ ] One-click merge: Keep newest, migrate parameters/history
- [ ] Prevent future duplicates at registration time
- [ ] Root cause: Are devices sending wrong serial? (firmware bug)
- [ ] Alert admin immediately when duplicate detected

---

### 6. Duplicate WAN MAC Address Report
**USS Equivalent**: Devices - Duplicate WAN MAC Address Device Records (Table)

**What it shows**: Multiple devices claiming same MAC (possible cloning or data issue)

**Automation Ideas**:
- [ ] Flag as potential security issue (MAC spoofing)
- [ ] Cross-reference with DHCP logs
- [ ] Show timeline: When did each device use this MAC?
- [ ] Auto-alert network security team
- [ ] One-click: "Investigate" opens both device records side-by-side

---

### 7. Devices with Empty MAC Address Report
**USS Equivalent**: Devices - Devices with empty MAC Address (Table)

**What it shows**: Incomplete device registration

**Automation Ideas**:
- [ ] Queue "Get MAC Address" task automatically
- [ ] Show which parameter path to query per device type
- [ ] Bulk action: "Fetch Missing MACs" for all affected devices
- [ ] Track success rate by device model

---

### 8. Devices Without WiFi Passphrase Report
**USS Equivalent**: Devices - Devices without WiFi Passphrase (Table)

**What it shows**: Devices where we couldn't retrieve WiFi password (security/support issue)

**Automation Ideas**:
- [ ] Attempt to fetch passphrase on next connection
- [ ] Flag devices where passphrase is intentionally hidden (TR-181 security)
- [ ] For Nokia Beacon G6: Note that Password2 algorithm is needed
- [ ] Cross-reference with backup - do we have it stored there?
- [ ] Alert: "WiFi passphrase needed for support call" when customer calls in

---

### 9. Active Alarms Report
**USS Equivalent**: Alarms - Active Alarms (Table)

**What it shows**: Current device issues/faults

**Automation Ideas**:
- [ ] Implement alarm tracking (from Inform events)
- [ ] Auto-categorize by severity (Critical, Major, Minor, Warning)
- [ ] Suggested remediation per alarm type:
  - "Link Down" → Check WAN connection, reboot
  - "High Temperature" → Check ventilation, schedule replacement
  - "Memory Low" → Reboot device
- [ ] Auto-create trouble ticket in external system
- [ ] Alarm correlation: Multiple devices in same area = outage?
- [ ] One-click: "Acknowledge" or "Resolve" alarm

---

### 10. NAT-ed Devices Report
**USS Equivalent**: Devices - NAT-ed Devices (Table)

**What it shows**: Devices behind NAT (can't receive connection requests)

**Automation Ideas**:
- [ ] Identify devices that need STUN/XMPP setup
- [ ] One-click: "Enable STUN" configuration
- [ ] Show which customers are affected by NAT limitations
- [ ] Suggest network topology changes
- [ ] For TR-069: May need UDP connection request or periodic inform reliance

---

### 11. STUN Enabled Devices Report
**USS Equivalent**: Devices - STUN Enabled Devices (Table)

**What it shows**: Devices using STUN for NAT traversal

**Automation Ideas**:
- [ ] Verify STUN is actually working (test connection request)
- [ ] Monitor STUN binding success rate
- [ ] Alert on STUN failures
- [ ] One-click: "Test STUN Connectivity"

---

### 12. Connection Request Failures Report
**USS Equivalent**: Devices - Remote Connect Request (CRSA) Failures (Table)

**What it shows**: Devices we can't reach for on-demand tasks

**Automation Ideas**:
- [ ] Categorize failure reasons:
  - NAT/firewall blocking
  - Wrong credentials
  - Device offline
  - IP address changed
- [ ] Suggested fix per failure type
- [ ] Auto-retry logic with exponential backoff
- [ ] Fall back to periodic inform if CR consistently fails
- [ ] Track CR success rate per device model

---

### 13. Feature Set Restore Failures Report
**USS Equivalent**: Devices - Feature Set Restore CPE Failures (Table)

**What it shows**: Devices where config restore failed

**Automation Ideas**:
- [ ] Show exactly which parameters failed
- [ ] Retry failed parameters individually
- [ ] Flag incompatible parameters (firmware version mismatch)
- [ ] Offer "Partial Restore" - apply only compatible settings
- [ ] Root cause by parameter path pattern

---

## Phase 2: Inventory & Fleet Reports

### 14. Device Firmware Report
**USS Equivalent**: Devices - Device Firmware Report (Table)

**Features**:
- [ ] List all firmware versions in fleet
- [ ] Count devices per version
- [ ] Highlight outdated firmware
- [ ] One-click: "Upgrade all devices on version X"
- [ ] Track firmware upgrade success rate

---

### 15. Device Type Report
**USS Equivalent**: Devices - Device Type Report (Table)

**Features**:
- [ ] Breakdown by manufacturer, model, product class
- [ ] Filter by online/offline status
- [ ] Export for inventory management

---

### 16. Devices by Vendor (Pie Chart)
**USS Equivalent**: Devices - Devices by Vendor (Pie Chart)

**Features**:
- [ ] Visual breakdown of fleet composition
- [ ] Click segment to drill down to device list

---

### 17. All Devices (CSV Export)
**USS Equivalent**: Devices - All Devices (CSV File)

**Features**:
- [ ] Customizable columns
- [ ] Include subscriber info
- [ ] Include last known parameters
- [ ] Schedule automated exports (daily/weekly)

---

### 18. Devices Created by Date/Month Reports
**USS Equivalent**: Devices - Devices Created by Date, by Month, Last 30 Days

**Features**:
- [ ] Growth tracking charts
- [ ] Correlate with sales/installations
- [ ] Identify deployment patterns

---

## Phase 3: Subscriber Reports

### 19. Subscribers Without Devices
**USS Equivalent**: Subscribers - Subscribers without Devices (Table)

**Automation Ideas**:
- [ ] Cross-reference with pending installations
- [ ] Flag billing issues (paying but no equipment?)
- [ ] Integration with work order system

---

### 20. Subscribers with Multiple Devices
**USS Equivalent**: Inferred from All Subscribers And Associated Devices

**Features**:
- [ ] Identify mesh/extender deployments
- [ ] Upsell opportunities
- [ ] Support complexity indicator

---

## Phase 4: Network Quality Reports

### 21. Speed Test Results
**USS Equivalent**: CAF - Speed Test Results (various)

**Features**:
- [ ] Historical speed test data
- [ ] Trending charts
- [ ] Compare to subscribed speed tier
- [ ] Alert on consistently low speeds

---

### 22. IP History Report
**USS Equivalent**: Devices - IP History Report (Table)

**Features**:
- [ ] Track IP changes over time
- [ ] Useful for troubleshooting
- [ ] Detect frequent DHCP renewals (instability indicator)

---

## Implementation Priority Order

1. **Immediate** (This Week):
   - Offline Devices with wake action
   - Excessive Informs detection
   - Duplicate Serial/MAC detection

2. **Short Term** (This Month):
   - 30+ Day Inactive
   - Devices without Subscriber
   - Device Firmware Report
   - All Devices CSV Export

3. **Medium Term** (Next Quarter):
   - Active Alarms system
   - Connection Request Failures
   - NAT-ed Devices
   - Speed Test trending

4. **Long Term**:
   - Full alarm correlation
   - Automated remediation workflows
   - Integration with external ticketing
   - Predictive analytics (device likely to fail)

---

## Technical Implementation Notes

### Database Tables Needed
- `device_alarms` - Track active/historical alarms
- `connection_request_logs` - Track CR attempts and results
- `device_ip_history` - Track IP changes over time
- `report_schedules` - Automated report generation

### API Endpoints Needed
- `GET /api/reports/{report-type}` - Fetch report data
- `POST /api/reports/{report-type}/export` - Export to CSV
- `POST /api/devices/{id}/wake` - Send connection request
- `POST /api/devices/bulk-action` - Bulk operations

### UI Components
- Report viewer with filtering, sorting, pagination
- Chart components (pie, bar, line, scatter)
- Bulk action toolbar
- Export button with format selection
- Automation wizard for remediation steps

---

## Success Metrics

- **Faster Issue Resolution**: Time from problem detection to fix
- **Reduced Manual Work**: Automation success rate
- **Proactive Detection**: Issues found before customer reports
- **Data Quality**: Reduction in orphaned/duplicate records
- **Fleet Visibility**: % of devices with complete data

---

*This document should be updated as features are implemented and new automation ideas emerge.*
