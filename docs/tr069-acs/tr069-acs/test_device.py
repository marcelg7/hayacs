"""
TR-069 Device Simulator
Simulates a CPE device connecting to the ACS for testing
"""
import requests
import xml.etree.ElementTree as ET
from datetime import datetime
import time
import sys

ACS_URL = "http://localhost:8080/cwmp"

# Sample device information
DEVICE_INFO = {
    'manufacturer': 'TestVendor',
    'oui': 'ABCDEF',
    'product_class': 'TestRouter',
    'serial_number': 'TEST123456'
}

def create_inform_message():
    """Create TR-069 Inform message"""
    envelope = ET.Element('{http://schemas.xmlsoap.org/soap/envelope/}Envelope')
    envelope.set('xmlns:soap', 'http://schemas.xmlsoap.org/soap/envelope/')
    envelope.set('xmlns:cwmp', 'urn:dslforum-org:cwmp-1-0')
    envelope.set('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance')
    
    # SOAP Header
    header = ET.SubElement(envelope, '{http://schemas.xmlsoap.org/soap/envelope/}Header')
    cwmp_id = ET.SubElement(header, '{urn:dslforum-org:cwmp-1-0}ID')
    cwmp_id.set('soap:mustUnderstand', '1')
    cwmp_id.text = '1234567890'
    
    # SOAP Body
    body = ET.SubElement(envelope, '{http://schemas.xmlsoap.org/soap/envelope/}Body')
    inform = ET.SubElement(body, '{urn:dslforum-org:cwmp-1-0}Inform')
    
    # DeviceId
    device_id = ET.SubElement(inform, 'DeviceId')
    manufacturer = ET.SubElement(device_id, 'Manufacturer')
    manufacturer.text = DEVICE_INFO['manufacturer']
    oui = ET.SubElement(device_id, 'OUI')
    oui.text = DEVICE_INFO['oui']
    product_class = ET.SubElement(device_id, 'ProductClass')
    product_class.text = DEVICE_INFO['product_class']
    serial_number = ET.SubElement(device_id, 'SerialNumber')
    serial_number.text = DEVICE_INFO['serial_number']
    
    # Event
    event = ET.SubElement(inform, 'Event')
    event.set('soap:arrayType', 'cwmp:EventStruct[2]')
    
    # Event 1: BOOTSTRAP
    event_struct1 = ET.SubElement(event, 'EventStruct')
    event_code1 = ET.SubElement(event_struct1, 'EventCode')
    event_code1.text = '0 BOOTSTRAP'
    event_key1 = ET.SubElement(event_struct1, 'CommandKey')
    event_key1.text = ''
    
    # Event 2: PERIODIC
    event_struct2 = ET.SubElement(event, 'EventStruct')
    event_code2 = ET.SubElement(event_struct2, 'EventCode')
    event_code2.text = '2 PERIODIC'
    event_key2 = ET.SubElement(event_struct2, 'CommandKey')
    event_key2.text = ''
    
    # MaxEnvelopes
    max_envelopes = ET.SubElement(inform, 'MaxEnvelopes')
    max_envelopes.text = '1'
    
    # CurrentTime
    current_time = ET.SubElement(inform, 'CurrentTime')
    current_time.text = datetime.utcnow().isoformat()
    
    # RetryCount
    retry_count = ET.SubElement(inform, 'RetryCount')
    retry_count.text = '0'
    
    # ParameterList
    param_list = ET.SubElement(inform, 'ParameterList')
    param_list.set('soap:arrayType', 'cwmp:ParameterValueStruct[8]')
    
    # Add sample parameters
    parameters = {
        'InternetGatewayDevice.DeviceInfo.Manufacturer': DEVICE_INFO['manufacturer'],
        'InternetGatewayDevice.DeviceInfo.ManufacturerOUI': DEVICE_INFO['oui'],
        'InternetGatewayDevice.DeviceInfo.ProductClass': DEVICE_INFO['product_class'],
        'InternetGatewayDevice.DeviceInfo.SerialNumber': DEVICE_INFO['serial_number'],
        'InternetGatewayDevice.DeviceInfo.SoftwareVersion': '1.0.0',
        'InternetGatewayDevice.DeviceInfo.HardwareVersion': '1.0',
        'InternetGatewayDevice.ManagementServer.ConnectionRequestURL': 'http://192.168.1.1:7547/',
        'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress': '203.0.113.1'
    }
    
    for name, value in parameters.items():
        param_struct = ET.SubElement(param_list, 'ParameterValueStruct')
        name_elem = ET.SubElement(param_struct, 'Name')
        name_elem.text = name
        value_elem = ET.SubElement(param_struct, 'Value')
        value_elem.set('xsi:type', 'xsd:string')
        value_elem.text = value
    
    return ET.tostring(envelope, encoding='unicode')


def parse_acs_response(response_text):
    """Parse ACS response"""
    try:
        root = ET.fromstring(response_text)
        body = root.find('.//{http://schemas.xmlsoap.org/soap/envelope/}Body')
        
        if body is None or len(body) == 0:
            return None, "Empty response (session end)"
        
        # Get the method name
        method = body[0]
        method_name = method.tag.split('}')[-1]
        
        return method_name, None
    except Exception as e:
        return None, f"Parse error: {e}"


def send_empty_response():
    """Send empty HTTP response to end session"""
    return ""


def simulate_device_session():
    """Simulate a complete TR-069 session"""
    print("=" * 60)
    print("TR-069 Device Simulator")
    print("=" * 60)
    print(f"Device: {DEVICE_INFO['manufacturer']} {DEVICE_INFO['product_class']}")
    print(f"Serial: {DEVICE_INFO['serial_number']}")
    print(f"ACS URL: {ACS_URL}")
    print("=" * 60)
    print()
    
    # Step 1: Send Inform
    print("[1] Sending Inform message to ACS...")
    inform_xml = create_inform_message()
    
    try:
        response = requests.post(
            ACS_URL,
            data=inform_xml,
            headers={
                'Content-Type': 'text/xml; charset=utf-8',
                'SOAPAction': ''
            }
        )
        
        if response.status_code != 200:
            print(f"❌ Error: ACS returned status {response.status_code}")
            print(response.text)
            return
        
        print("✅ Inform sent successfully")
        print(f"   Status: {response.status_code}")
        
        # Parse response
        method_name, error = parse_acs_response(response.text)
        
        if error:
            if "Empty response" in error:
                print(f"✅ {error}")
                return
            else:
                print(f"❌ {error}")
                return
        
        if method_name == 'InformResponse':
            print("✅ Received InformResponse from ACS")
        else:
            print(f"⚠️  Unexpected response: {method_name}")
        
        # Check for more messages
        print()
        print("[2] Checking for pending tasks...")
        
        # Send empty request to get next command
        response = requests.post(
            ACS_URL,
            data=send_empty_response(),
            headers={
                'Content-Type': 'text/xml; charset=utf-8',
                'SOAPAction': ''
            }
        )
        
        method_name, error = parse_acs_response(response.text)
        
        if error:
            if "Empty response" in error:
                print("✅ No pending tasks (session complete)")
            else:
                print(f"❌ {error}")
            return
        
        if method_name:
            print(f"📋 ACS requested: {method_name}")
            print()
            print("Response from ACS:")
            print(response.text[:500] + "..." if len(response.text) > 500 else response.text)
        
        print()
        print("=" * 60)
        print("Session completed successfully!")
        print("=" * 60)
        
    except requests.exceptions.ConnectionError:
        print("❌ Error: Could not connect to ACS")
        print("   Make sure the ACS is running on http://localhost:8080")
    except Exception as e:
        print(f"❌ Error: {e}")


def continuous_inform(interval=30):
    """Send periodic Inform messages"""
    print("Starting continuous Inform simulation...")
    print(f"Sending Inform every {interval} seconds")
    print("Press Ctrl+C to stop")
    print()
    
    count = 0
    try:
        while True:
            count += 1
            print(f"\n[Inform #{count}] {datetime.now().strftime('%H:%M:%S')}")
            simulate_device_session()
            time.sleep(interval)
    except KeyboardInterrupt:
        print("\n\nStopped by user")


if __name__ == "__main__":
    print()
    
    if len(sys.argv) > 1 and sys.argv[1] == "continuous":
        continuous_inform()
    else:
        simulate_device_session()
        print()
        print("💡 Tip: Run 'python test_device.py continuous' for periodic Inform messages")
        print()
