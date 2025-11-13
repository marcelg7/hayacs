#!/usr/bin/env python3
"""
TR-069 ACS Management CLI
Command-line interface for managing the ACS
"""
import argparse
import requests
import json
import sys
from tabulate import tabulate
from datetime import datetime

ACS_BASE_URL = "http://localhost:8080"


def list_devices():
    """List all devices"""
    try:
        response = requests.get(f"{ACS_BASE_URL}/api/devices")
        response.raise_for_status()
        devices = response.json()
        
        if not devices:
            print("No devices found.")
            return
        
        # Format data for table
        table_data = []
        for device in devices:
            last_inform = device.get('last_inform')
            if last_inform:
                last_inform = datetime.fromisoformat(last_inform).strftime('%Y-%m-%d %H:%M:%S')
            else:
                last_inform = 'Never'
            
            table_data.append([
                device['id'],
                device.get('manufacturer', '-'),
                device.get('product_class', '-'),
                device.get('serial_number', '-'),
                '🟢 Online' if device['online'] else '🔴 Offline',
                last_inform,
                device.get('software_version', '-')
            ])
        
        headers = ['Device ID', 'Manufacturer', 'Model', 'Serial', 'Status', 'Last Seen', 'SW Version']
        print(tabulate(table_data, headers=headers, tablefmt='grid'))
        print(f"\nTotal devices: {len(devices)}")
        
    except requests.exceptions.RequestException as e:
        print(f"Error: {e}")
        sys.exit(1)


def show_device(device_id):
    """Show device details"""
    try:
        response = requests.get(f"{ACS_BASE_URL}/api/devices/{device_id}")
        response.raise_for_status()
        device = response.json()
        
        print(f"\n{'='*60}")
        print(f"Device: {device_id}")
        print(f"{'='*60}")
        print(f"Manufacturer:       {device.get('manufacturer', '-')}")
        print(f"OUI:                {device.get('oui', '-')}")
        print(f"Product Class:      {device.get('product_class', '-')}")
        print(f"Serial Number:      {device.get('serial_number', '-')}")
        print(f"IP Address:         {device.get('ip_address', '-')}")
        print(f"Status:             {'🟢 Online' if device['online'] else '🔴 Offline'}")
        print(f"Software Version:   {device.get('software_version', '-')}")
        print(f"Hardware Version:   {device.get('hardware_version', '-')}")
        
        last_inform = device.get('last_inform')
        if last_inform:
            last_inform = datetime.fromisoformat(last_inform).strftime('%Y-%m-%d %H:%M:%S')
        print(f"Last Inform:        {last_inform or 'Never'}")
        
        first_seen = device.get('first_seen')
        if first_seen:
            first_seen = datetime.fromisoformat(first_seen).strftime('%Y-%m-%d %H:%M:%S')
        print(f"First Seen:         {first_seen or '-'}")
        
        print(f"CR URL:             {device.get('connection_request_url', '-')}")
        print(f"{'='*60}\n")
        
    except requests.exceptions.RequestException as e:
        print(f"Error: {e}")
        sys.exit(1)


def list_parameters(device_id):
    """List device parameters"""
    try:
        response = requests.get(f"{ACS_BASE_URL}/api/devices/{device_id}/parameters")
        response.raise_for_status()
        parameters = response.json()
        
        if not parameters:
            print("No parameters found.")
            return
        
        # Group parameters by category
        categories = {}
        for param in parameters:
            parts = param['name'].split('.')
            category = '.'.join(parts[:3]) if len(parts) > 3 else parts[0]
            
            if category not in categories:
                categories[category] = []
            categories[category].append(param)
        
        # Display by category
        for category, params in sorted(categories.items()):
            print(f"\n📁 {category}")
            print("─" * 80)
            
            table_data = []
            for param in params:
                # Shorten name by removing category prefix
                short_name = param['name'][len(category)+1:] if param['name'].startswith(category) else param['name']
                value = param['value']
                # Truncate long values
                if len(value) > 50:
                    value = value[:47] + "..."
                
                table_data.append([
                    short_name,
                    value,
                    param.get('type', '-'),
                ])
            
            print(tabulate(table_data, headers=['Parameter', 'Value', 'Type'], tablefmt='simple'))
        
        print(f"\nTotal parameters: {len(parameters)}")
        
    except requests.exceptions.RequestException as e:
        print(f"Error: {e}")
        sys.exit(1)


def reboot_device(device_id):
    """Reboot a device"""
    try:
        response = requests.post(f"{ACS_BASE_URL}/api/devices/{device_id}/reboot")
        response.raise_for_status()
        result = response.json()
        print(f"✅ {result['message']}")
        print(f"   Task ID: {result['task_id']}")
        print("   The device will reboot on its next connection to the ACS.")
    except requests.exceptions.RequestException as e:
        print(f"Error: {e}")
        sys.exit(1)


def factory_reset(device_id):
    """Factory reset a device"""
    print("⚠️  WARNING: This will factory reset the device!")
    confirm = input("Type 'yes' to confirm: ")
    
    if confirm.lower() != 'yes':
        print("Cancelled.")
        return
    
    try:
        response = requests.post(f"{ACS_BASE_URL}/api/devices/{device_id}/factory-reset")
        response.raise_for_status()
        result = response.json()
        print(f"✅ {result['message']}")
        print(f"   Task ID: {result['task_id']}")
    except requests.exceptions.RequestException as e:
        print(f"Error: {e}")
        sys.exit(1)


def get_parameter(device_id, parameter_names):
    """Get specific parameters from device"""
    task = {
        "type": "get_params",
        "parameters": {
            "names": parameter_names
        }
    }
    
    try:
        response = requests.post(
            f"{ACS_BASE_URL}/api/devices/{device_id}/tasks",
            json=task
        )
        response.raise_for_status()
        result = response.json()
        print(f"✅ Task created: {result['id']}")
        print("   The device will provide these parameters on its next connection.")
        print("\nRequested parameters:")
        for name in parameter_names:
            print(f"   - {name}")
    except requests.exceptions.RequestException as e:
        print(f"Error: {e}")
        sys.exit(1)


def set_parameter(device_id, parameter_values):
    """Set parameters on device"""
    # Parse parameter=value pairs
    params = {}
    for pv in parameter_values:
        if '=' not in pv:
            print(f"Error: Invalid format '{pv}'. Use 'parameter=value'")
            sys.exit(1)
        key, value = pv.split('=', 1)
        params[key] = value
    
    task = {
        "type": "set_params",
        "parameters": {
            "values": params
        }
    }
    
    try:
        response = requests.post(
            f"{ACS_BASE_URL}/api/devices/{device_id}/tasks",
            json=task
        )
        response.raise_for_status()
        result = response.json()
        print(f"✅ Task created: {result['id']}")
        print("   The device will apply these parameters on its next connection.")
        print("\nParameters to set:")
        for key, value in params.items():
            print(f"   - {key} = {value}")
    except requests.exceptions.RequestException as e:
        print(f"Error: {e}")
        sys.exit(1)


def list_tasks(device_id):
    """List tasks for device"""
    try:
        response = requests.get(f"{ACS_BASE_URL}/api/devices/{device_id}/tasks")
        response.raise_for_status()
        tasks = response.json()
        
        if not tasks:
            print("No tasks found.")
            return
        
        table_data = []
        for task in tasks:
            created = datetime.fromisoformat(task['created_at']).strftime('%Y-%m-%d %H:%M:%S')
            completed = ''
            if task['completed_at']:
                completed = datetime.fromisoformat(task['completed_at']).strftime('%Y-%m-%d %H:%M:%S')
            
            status_icon = {
                'pending': '⏳',
                'sent': '📤',
                'completed': '✅',
                'failed': '❌'
            }.get(task['status'], '❓')
            
            table_data.append([
                task['id'],
                task['task_type'],
                f"{status_icon} {task['status']}",
                created,
                completed or '-'
            ])
        
        headers = ['ID', 'Type', 'Status', 'Created', 'Completed']
        print(tabulate(table_data, headers=headers, tablefmt='grid'))
        print(f"\nTotal tasks: {len(tasks)}")
        
    except requests.exceptions.RequestException as e:
        print(f"Error: {e}")
        sys.exit(1)


def show_stats():
    """Show ACS statistics"""
    try:
        response = requests.get(f"{ACS_BASE_URL}/api/stats")
        response.raise_for_status()
        stats = response.json()
        
        print(f"\n{'='*40}")
        print("TR-069 ACS Statistics")
        print(f"{'='*40}")
        print(f"Total Devices:    {stats['total_devices']}")
        print(f"🟢 Online:        {stats['online_devices']}")
        print(f"🔴 Offline:       {stats['offline_devices']}")
        print(f"⏳ Pending Tasks: {stats['pending_tasks']}")
        print(f"{'='*40}\n")
        
    except requests.exceptions.RequestException as e:
        print(f"Error: {e}")
        sys.exit(1)


def main():
    parser = argparse.ArgumentParser(description='TR-069 ACS Management CLI')
    subparsers = parser.add_subparsers(dest='command', help='Commands')
    
    # List devices
    subparsers.add_parser('list', help='List all devices')
    
    # Show device
    show_parser = subparsers.add_parser('show', help='Show device details')
    show_parser.add_argument('device_id', help='Device ID')
    
    # Parameters
    params_parser = subparsers.add_parser('parameters', help='List device parameters')
    params_parser.add_argument('device_id', help='Device ID')
    
    # Get parameter
    get_parser = subparsers.add_parser('get', help='Get parameter values')
    get_parser.add_argument('device_id', help='Device ID')
    get_parser.add_argument('parameters', nargs='+', help='Parameter names')
    
    # Set parameter
    set_parser = subparsers.add_parser('set', help='Set parameter values')
    set_parser.add_argument('device_id', help='Device ID')
    set_parser.add_argument('parameters', nargs='+', help='parameter=value pairs')
    
    # Reboot
    reboot_parser = subparsers.add_parser('reboot', help='Reboot device')
    reboot_parser.add_argument('device_id', help='Device ID')
    
    # Factory reset
    reset_parser = subparsers.add_parser('factory-reset', help='Factory reset device')
    reset_parser.add_argument('device_id', help='Device ID')
    
    # Tasks
    tasks_parser = subparsers.add_parser('tasks', help='List device tasks')
    tasks_parser.add_argument('device_id', help='Device ID')
    
    # Stats
    subparsers.add_parser('stats', help='Show ACS statistics')
    
    args = parser.parse_args()
    
    if not args.command:
        parser.print_help()
        sys.exit(1)
    
    # Execute command
    if args.command == 'list':
        list_devices()
    elif args.command == 'show':
        show_device(args.device_id)
    elif args.command == 'parameters':
        list_parameters(args.device_id)
    elif args.command == 'get':
        get_parameter(args.device_id, args.parameters)
    elif args.command == 'set':
        set_parameter(args.device_id, args.parameters)
    elif args.command == 'reboot':
        reboot_device(args.device_id)
    elif args.command == 'factory-reset':
        factory_reset(args.device_id)
    elif args.command == 'tasks':
        list_tasks(args.device_id)
    elif args.command == 'stats':
        show_stats()


if __name__ == "__main__":
    main()
