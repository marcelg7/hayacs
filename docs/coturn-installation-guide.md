# Installing coturn STUN/TURN Server on hayacs.hay.net

## Prerequisites
SSH access to hayacs.hay.net with sudo privileges.

## Installation Steps

### 1. Install coturn package

**For AlmaLinux / RHEL / Rocky Linux:**
```bash
# Enable EPEL repository (coturn is in EPEL)
sudo dnf install -y epel-release

# Update package list
sudo dnf update -y

# Install coturn
sudo dnf install -y coturn
```

**For Debian / Ubuntu:**
```bash
# Update package list
sudo apt update

# Install coturn
sudo apt install -y coturn
```

### 2. Configure coturn

Create/edit the coturn configuration file:

```bash
sudo nano /etc/turnserver.conf
```

Use this configuration:

```conf
# Listen on all interfaces
listening-ip=0.0.0.0

# External IP (your server's public IP)
# Replace with your actual public IP
external-ip=YOUR_PUBLIC_IP

# STUN/TURN ports
listening-port=3478
tls-listening-port=5349

# Realm for authentication
realm=hayacs.hay.net
server-name=hayacs.hay.net

# Enable STUN
# TR-069 devices only need STUN, not TURN
fingerprint
lt-cred-mech

# Log settings
log-file=/var/log/turnserver.log
verbose

# Performance tuning
max-bps=0
bps-capacity=0

# Security
no-multicast-peers
no-cli
no-loopback-peers
no-tlsv1
no-tlsv1_1

# Allow all origins (for TR-069 devices)
no-stun-backward-compatibility
response-origin-only-with-rfc5780

# Disable TURN (we only need STUN for TR-069)
stun-only
```

### 3. Enable coturn service

**For AlmaLinux / RHEL / Rocky Linux:**
```bash
# Enable coturn to start on boot
sudo systemctl enable coturn

# Edit the sysconfig file to enable coturn (if it exists)
sudo sed -i 's/#TURNSERVER_ENABLED=1/TURNSERVER_ENABLED=1/' /etc/sysconfig/coturn 2>/dev/null || true
```

**For Debian / Ubuntu:**
```bash
# Enable coturn to start on boot
sudo systemctl enable coturn

# Edit the default file to enable coturn
sudo sed -i 's/#TURNSERVER_ENABLED=1/TURNSERVER_ENABLED=1/' /etc/default/coturn
```

### 4. Open firewall ports

**For AlmaLinux / RHEL / Rocky Linux (firewalld):**
```bash
# Allow STUN ports (UDP and TCP)
sudo firewall-cmd --permanent --add-port=3478/udp
sudo firewall-cmd --permanent --add-port=3478/tcp

# Optional: TLS ports for secure STUN
sudo firewall-cmd --permanent --add-port=5349/tcp
sudo firewall-cmd --permanent --add-port=5349/udp

# Reload firewall
sudo firewall-cmd --reload
```

**For Debian / Ubuntu (ufw):**
```bash
# Allow STUN port (UDP and TCP)
sudo ufw allow 3478/udp comment 'coturn STUN'
sudo ufw allow 3478/tcp comment 'coturn STUN'

# Optional: TLS port for secure STUN
sudo ufw allow 5349/tcp comment 'coturn STUNS'
sudo ufw allow 5349/udp comment 'coturn STUNS'
```

### 5. Start coturn service

```bash
# Start the service
sudo systemctl start coturn

# Check status
sudo systemctl status coturn

# View logs
sudo tail -f /var/log/turnserver.log
```

### 6. Get your server's public IP

```bash
curl -4 ifconfig.me
```

Update the `external-ip` in `/etc/turnserver.conf` with this IP, then restart coturn:

```bash
sudo systemctl restart coturn
```

## Testing STUN Server

From your local machine or another server, test the STUN server:

```bash
# Using stun client (install with: sudo apt install stun-client)
stunclient hayacs.hay.net 3478

# Or using netcat to check if port is open
nc -zvu hayacs.hay.net 3478
```

## Troubleshooting

### Check if coturn is listening
```bash
sudo netstat -tulpn | grep turnserver
```

### View coturn logs
```bash
sudo journalctl -u coturn -f
```

### Test from device perspective
```bash
# Install stun utilities
sudo apt install stuntman-client

# Test STUN binding request
stunclient hayacs.hay.net
```

## Next Steps

After coturn is running, update your TR-069 device to use:
- STUN Server: `hayacs.hay.net` (or your IP address)
- STUN Port: `3478`

The device should then successfully discover its UDP endpoint!
