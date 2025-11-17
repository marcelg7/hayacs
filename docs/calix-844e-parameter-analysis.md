# Calix 844E-1 TR-069 Parameter Analysis

## Device Information
- **Model**: 844E-1
- **Serial Number**: CXNK0083728A
- **Manufacturer**: Calix
- **Data Model**: InternetGatewayDevice:1.5
- **Software Version**: 12.2.12.9.1

## Key Findings for Troubleshooting Queries

### 1. WAN Connection (Non-Standard Numbering)
**Path**: `InternetGatewayDevice.WANDevice.3.WANConnectionDevice.1.WANIPConnection.14`

**Note**: Calix uses WANDevice.3 and WANIPConnection.14 (NOT the standard .1 instances)

**Available Parameters**:
- `ExternalIPAddress`: 163.182.232.246
- `SubnetMask`: 255.255.252.0
- `DefaultGateway`: 163.182.232.1
- `DNSServers`: 163.182.253.99,163.182.253.101
- `MACAddress`: d0:76:8f:00:42:6a
- `ConnectionStatus`: Connected
- `Uptime`: 1207 (seconds)
- `ConnectionType`: IP_Routed
- `AddressingType`: DHCP
- `NATEnabled`: TRUE

### 2. LAN Configuration
**Path**: `InternetGatewayDevice.LANDevice.1.LANHostConfigManagement`

**Available Parameters**:
- `IPInterface.1.IPInterfaceIPAddress`: 192.168.1.1
- `IPInterface.1.IPInterfaceSubnetMask`: 255.255.255.0
- `DHCPServerEnable`: TRUE
- `MinAddress`: 192.168.1.2
- `MaxAddress`: 192.168.1.254
- `DNSServers`: 163.182.253.99,163.182.253.101

### 3. WiFi Configuration (Multiple Instances)
**Path**: `InternetGatewayDevice.LANDevice.1.WLANConfiguration`

**Instance Layout**: 16 total instances (1-16)
- Instances 1-8: 2.4GHz SSIDs
- Instances 9-16: 5GHz SSIDs

**Currently Enabled**:
- **Instance 1** (2.4GHz Primary):
  - Enable: TRUE
  - Status: Up
  - SSID: marceltestssid2.4ghz
  - Channel: 11
  - Standard: bgn
  - BSSID: D0:76:8F:00:42:71

- **Instance 16** (5GHz Backhaul):
  - Enable: TRUE
  - Status: Up
  - SSID: 5GHz_Backhaul_SSID83728A
  - Channel: 136
  - Standard: ac
  - BSSID: c2:76:8f:00:42:72

**Available Parameters per WLAN Instance**:
- `Enable`
- `Status`
- `SSID`
- `Channel`
- `Standard`
- `BSSID`
- `AutoChannelEnable`
- `MaxBitRate`
- `BeaconType`
- `MACAddressControlEnabled`

**NOT Available**:
- `TransmitPower` - This parameter does NOT exist on Calix 844E

### 4. Connected Devices (Hosts)
**Path**: `InternetGatewayDevice.LANDevice.1.Hosts`

**Important**:
- `HostNumberOfEntries`: 0 (at time of dump)
- Individual Host instances only exist when devices are connected
- Cannot query Host.1, Host.2, etc. without first checking HostNumberOfEntries
- Querying non-existent Host instances causes SOAP Fault 9005

**Available When Hosts Exist**:
- `Host.{i}.HostName`
- `Host.{i}.IPAddress`
- `Host.{i}.MACAddress`
- `Host.{i}.Active`
- `Host.{i}.LeaseTimeRemaining`

## Recommended Troubleshooting Query Parameters

### For IGD (InternetGatewayDevice) Model:

#### LAN (6 parameters):
```
InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPInterface.1.IPInterfaceIPAddress
InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.IPInterface.1.IPInterfaceSubnetMask
InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.DHCPServerEnable
InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.MinAddress
InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.MaxAddress
InternetGatewayDevice.LANDevice.1.LANHostConfigManagement.DNSServers
```

#### WiFi - Known Active Instances (10 parameters):
```
InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Enable
InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID
InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel
InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Standard
InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Status
InternetGatewayDevice.LANDevice.1.WLANConfiguration.16.Enable
InternetGatewayDevice.LANDevice.1.WLANConfiguration.16.SSID
InternetGatewayDevice.LANDevice.1.WLANConfiguration.16.Channel
InternetGatewayDevice.LANDevice.1.WLANConfiguration.16.Standard
InternetGatewayDevice.LANDevice.1.WLANConfiguration.16.Status
```

#### Connected Devices (1 parameter):
```
InternetGatewayDevice.LANDevice.1.Hosts.HostNumberOfEntries
```

#### WAN (7 parameters) - Requires Non-Standard Instances:
```
InternetGatewayDevice.WANDevice.3.WANConnectionDevice.1.WANIPConnection.14.ExternalIPAddress
InternetGatewayDevice.WANDevice.3.WANConnectionDevice.1.WANIPConnection.14.SubnetMask
InternetGatewayDevice.WANDevice.3.WANConnectionDevice.1.WANIPConnection.14.DefaultGateway
InternetGatewayDevice.WANDevice.3.WANConnectionDevice.1.WANIPConnection.14.DNSServers
InternetGatewayDevice.WANDevice.3.WANConnectionDevice.1.WANIPConnection.14.MACAddress
InternetGatewayDevice.WANDevice.3.WANConnectionDevice.1.WANIPConnection.14.ConnectionStatus
InternetGatewayDevice.WANDevice.3.WANConnectionDevice.1.WANIPConnection.14.Uptime
```

## Issues Encountered

### 1. TransmitPower Not Supported
**Error**: SOAP Fault 9005 - Invalid Parameter Name
**Cause**: Querying `WLANConfiguration.{i}.TransmitPower`
**Solution**: Remove this parameter from queries

### 2. Host Instances Don't Exist
**Error**: SOAP Fault 9005 - Invalid Parameter Name
**Cause**: Querying `Host.1` through `Host.10` when HostNumberOfEntries = 0
**Solution**: Only query HostNumberOfEntries, not individual hosts (requires two-stage discovery)

### 3. Standard WAN Instances Don't Exist
**Error**: SOAP Fault 9005 - Invalid Parameter Name
**Cause**: Querying `WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.*`
**Solution**: Use device-specific instances (WANDevice.3, WANIPConnection.14) or implement discovery

## Two-Stage Discovery Approach (Future Enhancement)

To handle manufacturer-specific numbering, implement:

1. **Stage 1 - Discovery**:
   ```
   InternetGatewayDevice.WANDeviceNumberOfEntries
   InternetGatewayDevice.WANDevice.{i}.WANConnectionDevice.1.WANConnectionNumberOfEntries
   InternetGatewayDevice.LANDevice.1.Hosts.HostNumberOfEntries
   ```

2. **Stage 2 - Detailed Query**: Use discovered instance numbers to build dynamic queries

This would work across all manufacturers without hardcoding instance numbers.
