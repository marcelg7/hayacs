#!/bin/bash

# Automated coturn installation script for hayacs.hay.net
# Run this script on your server with: sudo bash install-coturn.sh
# Supports: AlmaLinux, RHEL, Rocky Linux, Debian, Ubuntu

set -e

echo "==========================================="
echo "coturn STUN Server Installation"
echo "For TR-069 UDP Connection Request"
echo "==========================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Error: Please run as root (use sudo)"
    exit 1
fi

# Detect OS
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    echo "Detected OS: $PRETTY_NAME"
else
    echo "Error: Cannot detect OS"
    exit 1
fi

# Detect public IP
echo "Detecting server public IP..."
PUBLIC_IP=$(curl -4 -s ifconfig.me)
echo "Detected public IP: $PUBLIC_IP"
echo ""

# Install coturn based on OS
echo "Installing coturn package..."
case "$OS" in
    almalinux|rhel|rocky|centos|fedora)
        echo "Installing for RHEL-based system..."
        # Enable EPEL repository (coturn is in EPEL)
        dnf install -y epel-release
        dnf update -y
        dnf install -y coturn
        ;;
    debian|ubuntu)
        echo "Installing for Debian-based system..."
        apt update
        apt install -y coturn
        ;;
    *)
        echo "Error: Unsupported OS: $OS"
        exit 1
        ;;
esac

# Backup original config
if [ -f /etc/turnserver.conf ]; then
    echo "Backing up original config..."
    cp /etc/turnserver.conf /etc/turnserver.conf.backup.$(date +%Y%m%d_%H%M%S)
fi

# Create coturn configuration
echo "Creating coturn configuration..."
cat > /etc/turnserver.conf << EOF
# coturn configuration for TR-069 UDP Connection Request
# Generated: $(date)

# Listen on all interfaces
listening-ip=0.0.0.0

# External IP (auto-detected)
external-ip=$PUBLIC_IP

# STUN/TURN ports
listening-port=3478
tls-listening-port=5349

# Realm for authentication
realm=hayacs.hay.net
server-name=hayacs.hay.net

# Enable STUN
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

# STUN-only mode (no TURN relay)
stun-only
EOF

echo "Configuration created at /etc/turnserver.conf"
echo ""

# Enable coturn
echo "Enabling coturn service..."
if [ -f /etc/default/coturn ]; then
    # Debian/Ubuntu location
    sed -i 's/#TURNSERVER_ENABLED=1/TURNSERVER_ENABLED=1/' /etc/default/coturn
elif [ -f /etc/sysconfig/coturn ]; then
    # RHEL/AlmaLinux location
    sed -i 's/#TURNSERVER_ENABLED=1/TURNSERVER_ENABLED=1/' /etc/sysconfig/coturn
fi

# Configure firewall
echo "Configuring firewall..."
if command -v firewall-cmd &> /dev/null; then
    echo "Adding firewalld rules (RHEL/AlmaLinux)..."
    firewall-cmd --permanent --add-port=3478/udp
    firewall-cmd --permanent --add-port=3478/tcp
    firewall-cmd --permanent --add-port=5349/tcp
    firewall-cmd --permanent --add-port=5349/udp
    firewall-cmd --reload
    echo "✓ firewalld rules added"
elif command -v ufw &> /dev/null; then
    echo "Adding UFW firewall rules (Debian/Ubuntu)..."
    ufw allow 3478/udp comment 'coturn STUN'
    ufw allow 3478/tcp comment 'coturn STUN'
    ufw allow 5349/tcp comment 'coturn STUNS'
    ufw allow 5349/udp comment 'coturn STUNS'
    echo "✓ UFW rules added"
else
    echo "⚠ Warning: No firewall detected. Please manually open ports 3478 and 5349 (UDP/TCP)"
fi
echo ""

# Start coturn
echo "Starting coturn service..."
systemctl enable coturn
systemctl restart coturn

# Wait a moment for service to start
sleep 2

# Check status
echo ""
echo "Checking coturn status..."
if systemctl is-active --quiet coturn; then
    echo "✓ coturn is running successfully!"
else
    echo "✗ Error: coturn failed to start"
    echo "Check logs with: sudo journalctl -u coturn -n 50"
    exit 1
fi

# Show listening ports
echo ""
echo "coturn is listening on:"
netstat -tulpn | grep turnserver || ss -tulpn | grep turnserver

echo ""
echo "==========================================="
echo "Installation Complete!"
echo "==========================================="
echo ""
echo "STUN Server Details:"
echo "  Address: hayacs.hay.net (or $PUBLIC_IP)"
echo "  Port: 3478"
echo "  Protocol: UDP/TCP"
echo ""
echo "Next steps:"
echo "1. Test STUN server: stunclient hayacs.hay.net 3478"
echo "2. Update TR-069 device to use hayacs.hay.net as STUN server"
echo "3. Monitor logs: sudo tail -f /var/log/turnserver.log"
echo ""
echo "To check status: sudo systemctl status coturn"
echo "To view logs: sudo journalctl -u coturn -f"
echo ""
