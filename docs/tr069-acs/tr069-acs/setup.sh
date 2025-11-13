#!/bin/bash

# TR-069 ACS Quick Setup Script

set -e

echo "=========================================="
echo "TR-069 ACS Setup"
echo "=========================================="
echo ""

# Check Python version
echo "Checking Python version..."
if ! command -v python3 &> /dev/null; then
    echo "❌ Python 3 is not installed. Please install Python 3.9 or higher."
    exit 1
fi

PYTHON_VERSION=$(python3 -c 'import sys; print(".".join(map(str, sys.version_info[:2])))')
echo "✅ Python $PYTHON_VERSION found"
echo ""

# Create virtual environment
if [ ! -d "venv" ]; then
    echo "Creating virtual environment..."
    python3 -m venv venv
    echo "✅ Virtual environment created"
else
    echo "✅ Virtual environment already exists"
fi
echo ""

# Activate virtual environment
echo "Activating virtual environment..."
source venv/bin/activate
echo ""

# Install dependencies
echo "Installing dependencies..."
pip install -r requirements.txt
echo "✅ Dependencies installed"
echo ""

# Create .env file if it doesn't exist
if [ ! -f ".env" ]; then
    echo "Creating .env file from template..."
    cp .env.example .env
    echo "✅ .env file created"
else
    echo "✅ .env file already exists"
fi
echo ""

# Make CLI executable
chmod +x acs_cli.py
echo "✅ CLI tool made executable"
echo ""

echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "To start the ACS server:"
echo "  python main.py"
echo ""
echo "To test with a simulated device:"
echo "  python test_device.py"
echo ""
echo "To use the CLI tool:"
echo "  ./acs_cli.py list"
echo "  ./acs_cli.py stats"
echo ""
echo "Access the web interface at:"
echo "  http://localhost:8080"
echo ""
echo "Configure your TR-069 devices with:"
echo "  ACS URL: http://your-server-ip:8080/cwmp"
echo ""
