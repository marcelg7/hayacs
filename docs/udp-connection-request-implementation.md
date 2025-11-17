# UDP Connection Request Implementation Guide

## Current Findings

### Device Support ✅
The Calix 844E **DOES support UDP Connection Request** via STUN (Session Traversal Utilities for NAT).

**Device Profile**: `UDPConnReq:1` (confirmed in DeviceSummary)

### Current Configuration

**From device dump (CXNK0083728A):**

```
ManagementServer:
  PeriodicInformInterval: 160 seconds (~2.6 minutes)
  ConnectionRequestURL: http://163.182.232.246:30005/ (HTTP - behind NAT, doesn't work)

  STUN Configuration (currently disabled):
    STUNEnable: FALSE
    STUNServerAddress: (null)
    STUNServerPort: 3478 (standard STUN port)
    STUNUsername: (null)
    STUNPassword: (null)
    STUNMaximumKeepAlivePeriod: 40 seconds
    STUNMinimumKeepAlivePeriod: 40 seconds
    UDPConnectionRequestAddress: (null) - will be populated after STUN is enabled
```

## How UDP Connection Request Works

### The Problem with HTTP Connection Request
- ACS tries to send HTTP request to device's WAN IP:port
- Fails because device is behind NAT (can't accept incoming connections)
- Results in 10-second timeout
- ACS must wait for next periodic inform

### The STUN/UDP Solution
1. **Device initiates STUN binding** to STUN server
2. **STUN server discovers** device's external IP and NAT-mapped port
3. **Device learns** its public UDP address (IP:port combo that works through NAT)
4. **Device sends** this `UDPConnectionRequestAddress` to ACS in next Inform
5. **ACS sends UDP packet** to that address to wake up device instantly
6. **NAT allows** the UDP response because it matches an existing binding
7. **Device connects** to ACS immediately (1-5 seconds instead of waiting for periodic inform)

## Implementation Requirements

### 1. STUN Server Options

**Option A: Public STUN Server (Easiest)**
- Google STUN: `stun.l.google.com:19302`
- Cloudflare STUN: `stun.cloudflare.com:3478`
- Twilio STUN: `stun.twilio.com:3478`
- Pros: Free, no setup
- Cons: Relies on third-party, potential privacy concerns

**Option B: Self-Hosted STUN Server (Recommended for production)**
- Install `coturn` STUN/TURN server on your infrastructure
- Full control, privacy, reliability
- Minimal resource requirements
- Setup time: ~30 minutes

### 2. Device Configuration (TR-069 SetParameterValues)

Configure each device with:

```php
$parameters = [
    'InternetGatewayDevice.ManagementServer.STUNEnable' => 'true',
    'InternetGatewayDevice.ManagementServer.STUNServerAddress' => 'stun.example.com', // Your STUN server
    'InternetGatewayDevice.ManagementServer.STUNServerPort' => [
        'value' => 3478,
        'type' => 'xsd:unsignedInt'
    ],
    // Optional: STUN credentials if using authenticated STUN
    // 'InternetGatewayDevice.ManagementServer.STUNUsername' => 'username',
    // 'InternetGatewayDevice.ManagementServer.STUNPassword' => 'password',
];
```

### 3. ACS Side Implementation

#### A. Store UDPConnectionRequestAddress
When device Informs with STUN enabled, it will include:
```
InternetGatewayDevice.ManagementServer.UDPConnectionRequestAddress
```
Example: `172.217.14.127:45678`

Store this in the `devices` table (new column).

#### B. Implement UDP Connection Request Sender

Instead of HTTP cURL request, send UDP packet:

```php
// Current (HTTP - doesn't work):
$ch = curl_init($device->connection_request_url);
// ... fails with timeout

// New (UDP - works instantly):
if ($device->udp_connection_request_address) {
    list($ip, $port) = explode(':', $device->udp_connection_request_address);

    $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

    // TR-069 Amendment 3 UDP packet format
    $packet = pack('C*',
        0x01,  // Version
        0x00,  // Type (Connection Request)
        // Include: Username, TS, ID, SigHash
    );

    socket_sendto($socket, $packet, strlen($packet), 0, $ip, $port);
    socket_close($socket);
}
```

#### C. Fallback Logic

```php
function sendConnectionRequest(Device $device) {
    // Try UDP first (if available)
    if ($device->udp_connection_request_address) {
        $result = $this->sendUdpConnectionRequest($device);
        if ($result) {
            Log::info('UDP Connection Request sent', ['device_id' => $device->id]);
            return true;
        }
    }

    // Fallback to HTTP (for devices without STUN or if UDP fails)
    return $this->sendHttpConnectionRequest($device);
}
```

## Implementation Steps

### Phase 1: STUN Server Setup
1. Choose STUN server (recommend starting with public Google STUN for testing)
2. For production: Set up coturn on a server

### Phase 2: Database Schema
```sql
ALTER TABLE devices ADD COLUMN udp_connection_request_address VARCHAR(255) NULL;
```

### Phase 3: Device Configuration
1. Create API endpoint to enable STUN on device(s)
2. Send SetParameterValues with STUN configuration
3. Wait for next Inform to receive UDPConnectionRequestAddress

### Phase 4: ACS UDP Sender
1. Implement UDP packet builder (TR-069 Amendment 3 format)
2. Implement UDP socket sender
3. Update triggerConnectionRequestForTask() to use UDP when available
4. Add fallback to HTTP

### Phase 5: Testing
1. Enable STUN on single test device
2. Verify UDPConnectionRequestAddress is received and stored
3. Test UDP Connection Request
4. Measure latency improvement (should be 1-5 seconds vs 160 seconds)

## Expected Results

### Before UDP Connection Request
- Click "Refresh Troubleshooting" → Wait ~160 seconds (periodic inform interval)
- 10-second timeout error in logs
- Total time: ~170 seconds

### After UDP Connection Request
- Click "Refresh Troubleshooting" → Device connects in 1-5 seconds
- No timeout errors
- Total time: ~5 seconds
- **97% latency reduction!**

## Scaling Considerations

For 7,000 devices with UDP Connection Request:
- **No additional load** on ACS (just sending small UDP packets)
- **Periodic Inform can stay at 10+ minutes** (only used for VALUE CHANGE events, not for commands)
- **Instant response** when needed
- **Much better user experience** for troubleshooting

## Security Notes

1. **STUN server** should be on trusted network or use authenticated STUN
2. **UDP packets** include cryptographic signature (TS, ID, SigHash) to prevent spoofing
3. **Device validates** signature before connecting to ACS
4. **No security downgrade** compared to HTTP connection request (which also has issues with NAT)

## References

- TR-069 Amendment 3 (adds UDP Connection Request)
- RFC 5389 (STUN Protocol)
- TR-069 Issue 2 Amendment 6 Section 3.2.2 (UDP Connection Request)

## Next Steps

Choose one:
1. **Quick Test**: Use Google STUN (`stun.l.google.com:19302`) and implement basic UDP sender
2. **Production Ready**: Set up coturn STUN server and implement full TR-069 Amendment 3 UDP packet format
