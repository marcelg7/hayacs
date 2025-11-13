"""
TR-069 ACS - Main Application
FastAPI server with CWMP endpoint and REST API
"""
from fastapi import FastAPI, Request, Depends, HTTPException
from fastapi.responses import Response, HTMLResponse
from fastapi.staticfiles import StaticFiles
from fastapi.middleware.cors import CORSMiddleware
from sqlalchemy.orm import Session
from typing import List, Optional
from datetime import datetime
import uuid

from cwmp_server import cwmp_server
from models import (
    init_db, get_db, Device, Parameter, Task, Session as DBSession
)

# Initialize FastAPI app
app = FastAPI(title="TR-069 ACS", version="1.0.0")

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize database
init_db()


# ============================================================================
# CWMP Endpoint (for device communication)
# ============================================================================

@app.post("/cwmp")
async def cwmp_endpoint(request: Request, db: Session = Depends(get_db)):
    """
    Main CWMP endpoint for TR-069 communication with CPE devices
    """
    # Get request body
    body = await request.body()
    xml_data = body.decode('utf-8')
    
    # Parse CWMP request
    parsed = cwmp_server.parse_soap_request(xml_data)
    
    if 'error' in parsed:
        return Response(
            content=cwmp_server.create_empty_response(),
            media_type="text/xml",
            status_code=400
        )
    
    method = parsed.get('method')
    params = parsed.get('params', {})
    
    response_xml = None
    
    # Handle Inform message
    if method == 'Inform':
        device_info = params.get('device_id', {})
        device_id = f"{device_info.get('oui', '')}-{device_info.get('product_class', '')}-{device_info.get('serial_number', '')}"
        
        # Update or create device
        device = db.query(Device).filter(Device.id == device_id).first()
        if not device:
            device = Device(
                id=device_id,
                manufacturer=device_info.get('manufacturer', ''),
                oui=device_info.get('oui', ''),
                product_class=device_info.get('product_class', ''),
                serial_number=device_info.get('serial_number', ''),
                first_seen=datetime.utcnow()
            )
            db.add(device)
        
        # Update device status
        device.last_inform = datetime.utcnow()
        device.online = True
        device.ip_address = request.client.host
        
        # Update parameters from Inform
        for param_name, param_value in params.get('parameters', {}).items():
            # Store important parameters
            if 'SoftwareVersion' in param_name:
                device.software_version = param_value
            elif 'HardwareVersion' in param_name:
                device.hardware_version = param_value
            elif 'ConnectionRequestURL' in param_name:
                device.connection_request_url = param_value
            
            # Store all parameters
            param = db.query(Parameter).filter(
                Parameter.device_id == device_id,
                Parameter.name == param_name
            ).first()
            
            if param:
                param.value = param_value
                param.last_updated = datetime.utcnow()
            else:
                param = Parameter(
                    device_id=device_id,
                    name=param_name,
                    value=param_value,
                    last_updated=datetime.utcnow()
                )
                db.add(param)
        
        db.commit()
        
        # Check for pending tasks
        pending_task = db.query(Task).filter(
            Task.device_id == device_id,
            Task.status == 'pending'
        ).first()
        
        if pending_task:
            # Send the task
            if pending_task.task_type == 'get_params':
                param_names = pending_task.parameters.get('names', [])
                response_xml = cwmp_server.create_get_parameter_values(param_names)
            elif pending_task.task_type == 'set_params':
                params_to_set = pending_task.parameters.get('values', {})
                response_xml = cwmp_server.create_set_parameter_values(params_to_set)
            elif pending_task.task_type == 'reboot':
                response_xml = cwmp_server.create_reboot()
            elif pending_task.task_type == 'factory_reset':
                response_xml = cwmp_server.create_factory_reset()
            
            # Mark task as sent
            pending_task.status = 'sent'
            db.commit()
        else:
            # No tasks, send InformResponse
            response_xml = cwmp_server.create_inform_response()
    
    # Handle other responses
    elif method == 'GetParameterValuesResponse':
        # Store the parameter values
        # (Would need to parse and store)
        response_xml = cwmp_server.create_empty_response()
    
    elif method == 'SetParameterValuesResponse':
        # Task completed
        response_xml = cwmp_server.create_empty_response()
    
    else:
        # Default: empty response
        response_xml = cwmp_server.create_empty_response()
    
    return Response(
        content=response_xml,
        media_type="text/xml",
        headers={"SOAPAction": ""}
    )


# ============================================================================
# REST API for Management
# ============================================================================

@app.get("/api/devices")
async def list_devices(db: Session = Depends(get_db)):
    """List all devices"""
    devices = db.query(Device).all()
    return [{
        'id': d.id,
        'manufacturer': d.manufacturer,
        'oui': d.oui,
        'product_class': d.product_class,
        'serial_number': d.serial_number,
        'ip_address': d.ip_address,
        'online': d.online,
        'last_inform': d.last_inform.isoformat() if d.last_inform else None,
        'software_version': d.software_version,
        'hardware_version': d.hardware_version,
        'tags': d.tags or []
    } for d in devices]


@app.get("/api/devices/{device_id}")
async def get_device(device_id: str, db: Session = Depends(get_db)):
    """Get device details"""
    device = db.query(Device).filter(Device.id == device_id).first()
    if not device:
        raise HTTPException(status_code=404, detail="Device not found")
    
    return {
        'id': device.id,
        'manufacturer': device.manufacturer,
        'oui': device.oui,
        'product_class': device.product_class,
        'serial_number': device.serial_number,
        'ip_address': device.ip_address,
        'online': device.online,
        'last_inform': device.last_inform.isoformat() if device.last_inform else None,
        'first_seen': device.first_seen.isoformat() if device.first_seen else None,
        'software_version': device.software_version,
        'hardware_version': device.hardware_version,
        'connection_request_url': device.connection_request_url,
        'tags': device.tags or [],
        'metadata': device.metadata or {}
    }


@app.get("/api/devices/{device_id}/parameters")
async def get_device_parameters(device_id: str, db: Session = Depends(get_db)):
    """Get all parameters for a device"""
    device = db.query(Device).filter(Device.id == device_id).first()
    if not device:
        raise HTTPException(status_code=404, detail="Device not found")
    
    parameters = db.query(Parameter).filter(Parameter.device_id == device_id).all()
    return [{
        'name': p.name,
        'value': p.value,
        'type': p.type,
        'writable': p.writable,
        'last_updated': p.last_updated.isoformat()
    } for p in parameters]


@app.post("/api/devices/{device_id}/tasks")
async def create_task(device_id: str, task: dict, db: Session = Depends(get_db)):
    """Create a task for a device"""
    device = db.query(Device).filter(Device.id == device_id).first()
    if not device:
        raise HTTPException(status_code=404, detail="Device not found")
    
    task_type = task.get('type')
    parameters = task.get('parameters', {})
    
    new_task = Task(
        device_id=device_id,
        task_type=task_type,
        parameters=parameters,
        status='pending'
    )
    db.add(new_task)
    db.commit()
    db.refresh(new_task)
    
    return {
        'id': new_task.id,
        'device_id': new_task.device_id,
        'task_type': new_task.task_type,
        'status': new_task.status,
        'created_at': new_task.created_at.isoformat()
    }


@app.get("/api/devices/{device_id}/tasks")
async def get_device_tasks(device_id: str, db: Session = Depends(get_db)):
    """Get all tasks for a device"""
    tasks = db.query(Task).filter(Task.device_id == device_id).order_by(Task.created_at.desc()).all()
    return [{
        'id': t.id,
        'task_type': t.task_type,
        'status': t.status,
        'created_at': t.created_at.isoformat(),
        'completed_at': t.completed_at.isoformat() if t.completed_at else None,
        'parameters': t.parameters,
        'result': t.result
    } for t in tasks]


@app.post("/api/devices/{device_id}/reboot")
async def reboot_device(device_id: str, db: Session = Depends(get_db)):
    """Reboot a device"""
    device = db.query(Device).filter(Device.id == device_id).first()
    if not device:
        raise HTTPException(status_code=404, detail="Device not found")
    
    task = Task(
        device_id=device_id,
        task_type='reboot',
        parameters={},
        status='pending'
    )
    db.add(task)
    db.commit()
    
    return {'message': 'Reboot task created', 'task_id': task.id}


@app.post("/api/devices/{device_id}/factory-reset")
async def factory_reset_device(device_id: str, db: Session = Depends(get_db)):
    """Factory reset a device"""
    device = db.query(Device).filter(Device.id == device_id).first()
    if not device:
        raise HTTPException(status_code=404, detail="Device not found")
    
    task = Task(
        device_id=device_id,
        task_type='factory_reset',
        parameters={},
        status='pending'
    )
    db.add(task)
    db.commit()
    
    return {'message': 'Factory reset task created', 'task_id': task.id}


@app.get("/api/stats")
async def get_stats(db: Session = Depends(get_db)):
    """Get system statistics"""
    total_devices = db.query(Device).count()
    online_devices = db.query(Device).filter(Device.online == True).count()
    pending_tasks = db.query(Task).filter(Task.status == 'pending').count()
    
    return {
        'total_devices': total_devices,
        'online_devices': online_devices,
        'offline_devices': total_devices - online_devices,
        'pending_tasks': pending_tasks
    }


# ============================================================================
# Web UI
# ============================================================================

@app.get("/", response_class=HTMLResponse)
async def root():
    """Serve the web UI"""
    return """
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>TR-069 ACS</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                background: #f5f7fa;
                color: #333;
            }
            .header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 2rem;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .header h1 {
                font-size: 2rem;
                font-weight: 600;
            }
            .header p {
                opacity: 0.9;
                margin-top: 0.5rem;
            }
            .container {
                max-width: 1400px;
                margin: 0 auto;
                padding: 2rem;
            }
            .stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.5rem;
                margin-bottom: 2rem;
            }
            .stat-card {
                background: white;
                padding: 1.5rem;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                transition: transform 0.2s;
            }
            .stat-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.12);
            }
            .stat-value {
                font-size: 2.5rem;
                font-weight: 700;
                color: #667eea;
                margin-bottom: 0.5rem;
            }
            .stat-label {
                color: #666;
                font-size: 0.9rem;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .section {
                background: white;
                padding: 2rem;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                margin-bottom: 2rem;
            }
            .section h2 {
                margin-bottom: 1.5rem;
                color: #333;
                font-size: 1.5rem;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th {
                text-align: left;
                padding: 1rem;
                background: #f8f9fa;
                font-weight: 600;
                color: #555;
                border-bottom: 2px solid #e9ecef;
            }
            td {
                padding: 1rem;
                border-bottom: 1px solid #e9ecef;
            }
            tr:hover {
                background: #f8f9fa;
            }
            .status-badge {
                display: inline-block;
                padding: 0.25rem 0.75rem;
                border-radius: 12px;
                font-size: 0.85rem;
                font-weight: 500;
            }
            .status-online {
                background: #d4edda;
                color: #155724;
            }
            .status-offline {
                background: #f8d7da;
                color: #721c24;
            }
            .btn {
                padding: 0.5rem 1rem;
                border: none;
                border-radius: 6px;
                cursor: pointer;
                font-size: 0.9rem;
                font-weight: 500;
                transition: all 0.2s;
                margin-right: 0.5rem;
            }
            .btn-primary {
                background: #667eea;
                color: white;
            }
            .btn-primary:hover {
                background: #5568d3;
            }
            .btn-danger {
                background: #dc3545;
                color: white;
            }
            .btn-danger:hover {
                background: #c82333;
            }
            .btn-warning {
                background: #ffc107;
                color: #333;
            }
            .btn-warning:hover {
                background: #e0a800;
            }
            .loading {
                text-align: center;
                padding: 2rem;
                color: #666;
            }
            .device-link {
                color: #667eea;
                text-decoration: none;
                font-weight: 500;
            }
            .device-link:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>🌐 TR-069 ACS Management</h1>
            <p>Auto Configuration Server for CPE Device Management</p>
        </div>
        
        <div class="container">
            <div class="stats" id="stats">
                <div class="stat-card">
                    <div class="stat-value" id="totalDevices">-</div>
                    <div class="stat-label">Total Devices</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #28a745;" id="onlineDevices">-</div>
                    <div class="stat-label">Online</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #dc3545;" id="offlineDevices">-</div>
                    <div class="stat-label">Offline</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" style="color: #ffc107;" id="pendingTasks">-</div>
                    <div class="stat-label">Pending Tasks</div>
                </div>
            </div>
            
            <div class="section">
                <h2>Devices</h2>
                <div id="devicesTable" class="loading">Loading devices...</div>
            </div>
        </div>
        
        <script>
            let devices = [];
            
            async function loadStats() {
                try {
                    const response = await fetch('/api/stats');
                    const stats = await response.json();
                    document.getElementById('totalDevices').textContent = stats.total_devices;
                    document.getElementById('onlineDevices').textContent = stats.online_devices;
                    document.getElementById('offlineDevices').textContent = stats.offline_devices;
                    document.getElementById('pendingTasks').textContent = stats.pending_tasks;
                } catch (error) {
                    console.error('Error loading stats:', error);
                }
            }
            
            async function loadDevices() {
                try {
                    const response = await fetch('/api/devices');
                    devices = await response.json();
                    renderDevices();
                } catch (error) {
                    document.getElementById('devicesTable').innerHTML = 
                        '<p style="color: #dc3545;">Error loading devices</p>';
                }
            }
            
            function renderDevices() {
                const container = document.getElementById('devicesTable');
                
                if (devices.length === 0) {
                    container.innerHTML = '<p style="color: #666;">No devices registered yet. Connect a TR-069 device to get started.</p>';
                    return;
                }
                
                const table = `
                    <table>
                        <thead>
                            <tr>
                                <th>Device ID</th>
                                <th>Manufacturer</th>
                                <th>Model</th>
                                <th>Serial Number</th>
                                <th>IP Address</th>
                                <th>Software Version</th>
                                <th>Status</th>
                                <th>Last Seen</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${devices.map(device => `
                                <tr>
                                    <td><a href="#" class="device-link" onclick="viewDevice('${device.id}'); return false;">${device.id}</a></td>
                                    <td>${device.manufacturer || '-'}</td>
                                    <td>${device.product_class || '-'}</td>
                                    <td>${device.serial_number || '-'}</td>
                                    <td>${device.ip_address || '-'}</td>
                                    <td>${device.software_version || '-'}</td>
                                    <td>
                                        <span class="status-badge ${device.online ? 'status-online' : 'status-offline'}">
                                            ${device.online ? 'Online' : 'Offline'}
                                        </span>
                                    </td>
                                    <td>${device.last_inform ? new Date(device.last_inform).toLocaleString() : 'Never'}</td>
                                    <td>
                                        <button class="btn btn-primary" onclick="viewDevice('${device.id}')">View</button>
                                        <button class="btn btn-warning" onclick="rebootDevice('${device.id}')">Reboot</button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
                
                container.innerHTML = table;
            }
            
            async function viewDevice(deviceId) {
                alert('Device details view - Device ID: ' + deviceId + '\n\nIn a full implementation, this would show detailed device info, parameters, and management options.');
            }
            
            async function rebootDevice(deviceId) {
                if (!confirm('Are you sure you want to reboot this device?')) return;
                
                try {
                    const response = await fetch(`/api/devices/${deviceId}/reboot`, {
                        method: 'POST'
                    });
                    const result = await response.json();
                    alert('Reboot task created! The device will reboot on next check-in.');
                    loadStats();
                } catch (error) {
                    alert('Error creating reboot task: ' + error.message);
                }
            }
            
            // Load data on page load
            loadStats();
            loadDevices();
            
            // Refresh every 30 seconds
            setInterval(() => {
                loadStats();
                loadDevices();
            }, 30000);
        </script>
    </body>
    </html>
    """


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8080)
