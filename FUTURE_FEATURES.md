# Future Features List

**Last Updated**: November 21, 2025

This document tracks features and enhancements to be considered for future development.

## Task Manager Enhancements

### Desktop Notifications
- **Priority**: Medium
- **Description**: Browser notifications when tasks complete while task panel is collapsed
- **Use Case**: Tech working in another tab gets notified when long-running operation completes
- **Dependencies**: Browser notification API permissions
- **Notes**: Particularly useful for long operations like "Get Everything" (2-8 minutes)

## Device Management

### Bulk Operations
- **Priority**: High
- **Description**: Select multiple devices and perform operations (reboot, backup, firmware update)
- **Use Case**: Update firmware across all Beacon G6 devices in one operation
- **Notes**: Would need careful queuing and progress tracking

### Device Grouping
- **Priority**: Medium
- **Description**: Create custom device groups (e.g., "Building A", "Customer Sites", "Test Devices")
- **Use Case**: Organize 12,000+ devices into logical groups
- **Notes**: Could integrate with bulk operations

### Scheduled Tasks
- **Priority**: Medium
- **Description**: Schedule backups, firmware updates, or parameter changes for specific times
- **Use Case**: Backup all devices nightly at 2 AM, update firmware during maintenance window
- **Dependencies**: Laravel job scheduling, cron

## Configuration Management

### Configuration Templates
- **Priority**: High
- **Description**: Save parameter sets as templates, apply to multiple devices
- **Use Case**: "Residential WiFi Standard" template - apply to all new Beacon G6 deployments
- **Notes**: Already have backup/restore, templates are next logical step

### Configuration Diff
- **Priority**: Medium
- **Description**: Compare two backups or two devices to see parameter differences
- **Use Case**: "What changed between this backup and current state?"
- **Notes**: Would be very useful for troubleshooting

### Parameter Validation
- **Priority**: Low
- **Description**: Validate parameters before sending (e.g., channel must be 1-11, IP must be valid)
- **Use Case**: Prevent invalid parameter sets from being sent to device
- **Notes**: Would require device model definitions

## Reporting & Analytics

### Device Health Dashboard
- **Priority**: Medium
- **Description**: Aggregate view of device health (uptime, errors, signal strength, etc.)
- **Use Case**: Quick overview of network health across all 12,000 devices
- **Notes**: Could show trends, alerts for devices not checking in

### Parameter Change History
- **Priority**: Low
- **Description**: Track all parameter changes over time with who/when/what
- **Use Case**: Audit trail, troubleshooting "what changed?"
- **Notes**: Already have tasks table, could enhance with detailed parameter tracking

### Firmware Version Report
- **Priority**: Medium
- **Description**: Report showing firmware versions across fleet, outdated devices
- **Use Case**: Identify devices needing firmware updates
- **Notes**: Data already available, just needs reporting view

## Advanced Features

### Connection Request (ACS-initiated)
- **Priority**: High (for production)
- **Description**: ACS can initiate connection to device (instead of waiting for periodic inform)
- **Use Case**: Immediate parameter changes, emergency reboots
- **Notes**: Requires device connection request URL/auth, NAT traversal

### Custom Scripts/Automation
- **Priority**: Low
- **Description**: Define custom workflows (e.g., "if WiFi channel has interference, auto-change")
- **Use Case**: Automated remediation, advanced power users
- **Notes**: Complex feature, needs careful design

### API for External Systems
- **Priority**: Medium
- **Description**: REST API for other systems to query/manage devices
- **Use Case**: Integration with billing system, customer portal, monitoring tools
- **Notes**: Already have internal API, would need auth/rate limiting

## UI/UX Improvements

### Dark Mode
- **Priority**: Low
- **Description**: Full dark theme option
- **Use Case**: Techs working in NOC with low light
- **Notes**: Theme switcher already exists, might already support dark mode

### Keyboard Shortcuts
- **Priority**: Low
- **Description**: Power user shortcuts (e.g., 'r' for refresh, '/' for search)
- **Use Case**: Speed up common operations for frequent users
- **Notes**: Would need to not conflict with browser shortcuts

### Export Task History
- **Priority**: Low
- **Description**: Export task history to CSV/PDF for reporting
- **Use Case**: Monthly reports, troubleshooting documentation
- **Notes**: Similar to parameter CSV export

## Integration

### NISC USS Migration Tool
- **Priority**: High (for deployment)
- **Description**: Automated migration of devices from USS to Hay ACS
- **Use Case**: Move 12,000+ devices without manual reconfiguration
- **Notes**: Would need USS API access or bulk parameter export

### Nokia Corteca Comparison
- **Priority**: Medium
- **Description**: Feature comparison matrix, migration path if needed
- **Use Case**: Decision making for Nokia vs Hay ACS
- **Notes**: Document for management decision

---

## Completed Features

This section tracks features that were on the future list and are now implemented:

- ✅ CSV Parameter Export (Nov 2025)
- ✅ Configuration Backup/Restore (Nov 2025)
- ✅ Smart Parameter Search with debounce (Nov 2025)
- ✅ Get Everything with automatic chunking (Nov 2025)
- ✅ Task Queue with progress tracking (Nov 2025)
- ✅ Non-blocking Task Manager UI (Nov 2025)

---

**Note**: Priority levels are subjective and should be reviewed with stakeholders before implementation.
