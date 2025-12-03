# Device Groups & Policy Management System - Schema Design

**Created**: November 28, 2025
**Status**: Design Phase

---

## Overview

This system enables administrators to:
1. Create device groups with automatic membership rules
2. Assign workflows (task sequences) to groups
3. Schedule and rate-limit workflow execution
4. Monitor progress and view audit logs

---

## Database Schema

### 1. `device_groups` - Group definitions

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `name` | string(100) | Group name (e.g., "Beacon G6 - Needs Firmware") |
| `description` | text | Optional description |
| `match_type` | enum | `all` (AND) or `any` (OR) for rule matching |
| `is_active` | boolean | Enable/disable group |
| `priority` | int | For ordering when device matches multiple groups |
| `created_by` | foreignId | User who created |
| `updated_by` | foreignId | User who last modified |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### 2. `device_group_rules` - Membership criteria

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `device_group_id` | foreignId | Parent group |
| `field` | string(100) | Device field to match (see list below) |
| `operator` | enum | `equals`, `not_equals`, `contains`, `not_contains`, `starts_with`, `ends_with`, `less_than`, `greater_than`, `regex`, `in`, `not_in`, `is_null`, `is_not_null` |
| `value` | text | Value to compare (JSON for `in`/`not_in`) |
| `order` | int | Rule evaluation order |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Matchable Fields:**
- `oui` - Manufacturer OUI
- `manufacturer` - Manufacturer name
- `product_class` - Product class/model
- `software_version` - Current firmware version
- `hardware_version` - Hardware version
- `data_model` - TR-098 or TR-181
- `serial_number` - Device serial
- `ip_address` - Current IP
- `online` - Online status (boolean)
- `subscriber_id` - Linked subscriber (null check)
- `tags` - Device tags (JSON contains)
- `last_inform` - Last inform time (for stale devices)
- `created_at` - First seen date

### 3. `group_workflows` - Task sequences assigned to groups

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `device_group_id` | foreignId | Target group |
| `name` | string(100) | Workflow name |
| `description` | text | Optional description |
| `task_type` | string(50) | Task type (see list below) |
| `task_parameters` | json | Task-specific parameters |
| `schedule_type` | enum | `immediate`, `scheduled`, `recurring`, `on_connect` |
| `schedule_config` | json | Schedule details (see below) |
| `rate_limit` | int | Max devices per hour (0 = unlimited) |
| `max_concurrent` | int | Max concurrent executions (0 = unlimited) |
| `retry_count` | int | Retries on failure (default 0) |
| `retry_delay_minutes` | int | Delay between retries |
| `stop_on_failure_percent` | int | Pause workflow if X% fail (0 = never) |
| `is_active` | boolean | Enable/disable workflow |
| `priority` | int | Execution priority |
| `run_once_per_device` | boolean | Only execute once per device (track in executions) |
| `depends_on_workflow_id` | foreignId | Must complete before this workflow runs (nullable) |
| `created_by` | foreignId | User who created |
| `updated_by` | foreignId | User who last modified |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Task Types:**
- `firmware_upgrade` - Download firmware file
- `set_parameter_values` - Set parameters
- `get_parameter_values` - Get parameters (for auditing)
- `reboot` - Reboot device
- `factory_reset` - Factory reset
- `backup` - Create config backup
- `restore` - Restore from backup
- `custom` - Custom task sequence

**Schedule Config Examples:**

```json
// Immediate
{ "type": "immediate" }

// Scheduled (one-time)
{
  "type": "scheduled",
  "start_at": "2025-12-01T02:00:00Z",
  "end_at": "2025-12-01T05:00:00Z"
}

// Recurring (maintenance window)
{
  "type": "recurring",
  "days": ["mon", "tue", "wed", "thu", "fri"],
  "start_time": "02:00",
  "end_time": "05:00",
  "timezone": "America/Toronto"
}

// On device connect (useful for upgrades)
{
  "type": "on_connect",
  "delay_seconds": 30
}
```

### 4. `workflow_executions` - Per-device execution tracking

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `group_workflow_id` | foreignId | Parent workflow |
| `device_id` | string(150) | Target device |
| `task_id` | foreignId | Created task (nullable until task created) |
| `status` | enum | `pending`, `queued`, `in_progress`, `completed`, `failed`, `skipped`, `cancelled` |
| `scheduled_at` | timestamp | When execution is scheduled |
| `started_at` | timestamp | When task was sent to device |
| `completed_at` | timestamp | When task completed |
| `attempt` | int | Current attempt number |
| `result` | json | Execution result/error details |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Indexes:**
- `(group_workflow_id, device_id)` - Unique, prevents duplicate executions
- `(group_workflow_id, status)` - For counting pending/completed
- `(device_id, status)` - For finding device's pending workflows

### 5. `workflow_logs` - Audit trail

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `group_workflow_id` | foreignId | Related workflow |
| `workflow_execution_id` | foreignId | Related execution (nullable) |
| `device_id` | string(150) | Related device (nullable) |
| `level` | enum | `info`, `warning`, `error` |
| `message` | text | Log message |
| `context` | json | Additional context data |
| `created_at` | timestamp | |

### 6. `device_group_memberships` - Cached group membership (optional, for performance)

| Column | Type | Description |
|--------|------|-------------|
| `device_id` | string(150) | Device ID |
| `device_group_id` | foreignId | Group ID |
| `matched_at` | timestamp | When membership was evaluated |
| `created_at` | timestamp | |

**Primary Key:** `(device_id, device_group_id)`

---

## Example Workflows

### 1. Firmware Upgrade for Beacon G6

**Group:** "Beacon G6 - Needs 25.03 Firmware"
```
Rules (match_type: all):
  - product_class equals "Beacon G6"
  - software_version not_equals "3FE49996IJMK14"
  - online equals true
```

**Workflow:**
```
name: "Upgrade to 25.03"
task_type: firmware_upgrade
task_parameters: {
  "firmware_id": 2,
  "file_type": "1 Firmware Upgrade Image"
}
schedule_type: recurring
schedule_config: {
  "days": ["mon", "tue", "wed", "thu", "fri"],
  "start_time": "02:00",
  "end_time": "05:00",
  "timezone": "America/Toronto"
}
rate_limit: 50
run_once_per_device: true
```

### 2. TR-098 to TR-181 Migration

**Group:** "Beacon G6 - Ready for TR-181 Migration"
```
Rules (match_type: all):
  - product_class equals "Beacon G6"
  - software_version equals "3FE49996IJMK14"
  - data_model equals "TR-098"
```

**Workflow 1:** "Set ConfigMigration Flag"
```
task_type: set_parameter_values
task_parameters: {
  "values": {
    "InternetGatewayDevice.DeviceInfo.X_ALU-COM_ConfigMigration": "1"
  }
}
schedule_type: on_connect
priority: 1
```

**Workflow 2:** "Push TR-181 Pre-Config"
```
task_type: download
task_parameters: {
  "file_type": "3 Vendor Configuration File",
  "url": "https://hayacs.hay.net/files/beacon-g6-pre-config-hayacs-tr181.xml"
}
schedule_type: on_connect
priority: 2
depends_on_workflow: 1
```

### 3. Nightly Backup for All Devices

**Group:** "All Active Devices"
```
Rules (match_type: all):
  - online equals true
```

**Workflow:**
```
name: "Nightly Backup"
task_type: backup
schedule_type: recurring
schedule_config: {
  "days": ["sun"],
  "start_time": "03:00",
  "end_time": "06:00"
}
rate_limit: 100
```

---

## UI Wireframes

### Device Groups List (`/admin/device-groups`)

```
+----------------------------------------------------------+
| Device Groups                              [+ New Group] |
+----------------------------------------------------------+
| Name                  | Devices | Workflows | Status     |
+----------------------------------------------------------+
| Beacon G6 - Needs FW  | 3,245   | 1         | Active     |
| TR-181 Migration      | 412     | 2         | Active     |
| All Active Devices    | 8,234   | 1         | Active     |
| Test Group            | 5       | 0         | Inactive   |
+----------------------------------------------------------+
```

### Device Group Edit (`/admin/device-groups/1/edit`)

```
+----------------------------------------------------------+
| Edit Group: Beacon G6 - Needs Firmware                   |
+----------------------------------------------------------+
| General | Rules | Workflows | Devices | Logs             |
+----------------------------------------------------------+
|                                                          |
| Name: [Beacon G6 - Needs Firmware_________________]      |
| Description: [Devices needing 25.03 upgrade______]       |
| Match Type: (x) All rules (AND)  ( ) Any rule (OR)       |
| Active: [x]                                              |
|                                                          |
| MEMBERSHIP RULES                          [+ Add Rule]   |
| +------------------------------------------------------+ |
| | product_class | equals     | Beacon G6      | [x]   | |
| | software_ver  | not_equals | 3FE49996IJMK14 | [x]   | |
| | online        | equals     | true           | [x]   | |
| +------------------------------------------------------+ |
|                                                          |
| Matching Devices: 3,245                    [Preview]     |
|                                                          |
|                              [Cancel] [Save Changes]     |
+----------------------------------------------------------+
```

### Workflow Execution Dashboard (`/admin/device-groups/1/workflows/1`)

```
+----------------------------------------------------------+
| Workflow: Upgrade to 25.03                               |
| Group: Beacon G6 - Needs Firmware                        |
+----------------------------------------------------------+
| Status: Running                    [Pause] [Cancel]      |
+----------------------------------------------------------+
|                                                          |
| Progress:  [=========>                    ] 28%          |
|                                                          |
| +------------+------------+------------+------------+    |
| |  Pending   | In Progress| Completed  |   Failed   |    |
| |   2,337    |     12     |    892     |     4      |    |
| +------------+------------+------------+------------+    |
|                                                          |
| Rate: 48 devices/hour (limit: 50)                        |
| Started: Nov 28, 2025 2:00 AM                            |
| ETA: Nov 30, 2025 4:00 AM                                |
|                                                          |
| RECENT EXECUTIONS                                        |
| +------------------------------------------------------+ |
| | Device           | Status    | Time      | Details  | |
| | ALCLFD0A7BE6     | Completed | 2:45 AM   | [View]   | |
| | ALCLFD0FC633     | Running   | 2:44 AM   | [View]   | |
| | ALCLFD0A8C12     | Failed    | 2:43 AM   | [View]   | |
| | ALCLFD0B1234     | Queued    | -         | -        | |
| +------------------------------------------------------+ |
|                                                          |
+----------------------------------------------------------+
```

---

## Implementation Order

1. **Phase 1: Database & Models**
   - Create migrations
   - Create Eloquent models with relationships
   - Add device group membership evaluation service

2. **Phase 2: Admin UI - Groups**
   - Device groups CRUD
   - Rule builder UI
   - Device preview/count

3. **Phase 3: Admin UI - Workflows**
   - Workflow CRUD
   - Task parameter editors
   - Schedule configuration

4. **Phase 4: Execution Engine**
   - Scheduler command (runs every minute)
   - Rate limiting logic
   - Integration with existing Task system

5. **Phase 5: Monitoring**
   - Execution dashboard
   - Audit logs
   - Email/notification on failures

---

## Questions to Resolve

1. **Workflow Dependencies**: Should workflows be able to depend on other workflows completing first? (e.g., set ConfigMigration before pushing pre-config)

2. **Manual Overrides**: Can admins manually add/remove devices from groups regardless of rules?

3. **Conflict Resolution**: What happens if a device matches multiple groups with conflicting workflows?

4. **Rollback**: Should we support automatic rollback if a workflow fails (e.g., restore backup after failed upgrade)?

5. **Approval Gates**: Should some workflows require manual approval before proceeding (e.g., after 10% complete, pause for review)?
