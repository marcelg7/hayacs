"""
TR-069 CWMP Protocol Server
Handles SOAP/XML communication with CPE devices
"""
import xml.etree.ElementTree as ET
from datetime import datetime
from typing import Optional, Dict, Any
import uuid

# SOAP namespaces
NAMESPACES = {
    'soap': 'http://schemas.xmlsoap.org/soap/envelope/',
    'cwmp': 'urn:dslforum-org:cwmp-1-0',
    'xsd': 'http://www.w3.org/2001/XMLSchema',
    'xsi': 'http://www.w3.org/2001/XMLSchema-instance'
}

# Register namespaces for pretty XML
for prefix, uri in NAMESPACES.items():
    ET.register_namespace(prefix, uri)


class CWMPServer:
    """Handles TR-069 CWMP protocol communication"""
    
    def __init__(self):
        self.pending_commands = {}  # device_id -> list of commands
    
    def parse_soap_request(self, xml_data: str) -> Dict[str, Any]:
        """Parse incoming SOAP request from CPE"""
        try:
            root = ET.fromstring(xml_data)
            
            # Find the CWMP method
            body = root.find('soap:Body', NAMESPACES)
            if body is None:
                return {'error': 'No SOAP Body found'}
            
            # Get the first child of Body (the CWMP method)
            method = body[0]
            method_name = method.tag.split('}')[-1]  # Remove namespace
            
            result = {
                'method': method_name,
                'params': {}
            }
            
            # Parse method-specific parameters
            if method_name == 'Inform':
                result['params'] = self._parse_inform(method)
            elif method_name == 'TransferCompleteResponse':
                result['params'] = self._parse_transfer_complete(method)
            elif method_name == 'GetRPCMethodsResponse':
                result['params'] = self._parse_rpc_methods_response(method)
            
            return result
            
        except ET.ParseError as e:
            return {'error': f'XML Parse Error: {str(e)}'}
    
    def _parse_inform(self, method: ET.Element) -> Dict[str, Any]:
        """Parse Inform message from CPE"""
        params = {}
        
        # Extract DeviceId
        device_id = method.find('.//DeviceId', NAMESPACES)
        if device_id is not None:
            params['device_id'] = {
                'manufacturer': device_id.find('Manufacturer', NAMESPACES).text if device_id.find('Manufacturer', NAMESPACES) is not None else '',
                'oui': device_id.find('OUI', NAMESPACES).text if device_id.find('OUI', NAMESPACES) is not None else '',
                'product_class': device_id.find('ProductClass', NAMESPACES).text if device_id.find('ProductClass', NAMESPACES) is not None else '',
                'serial_number': device_id.find('SerialNumber', NAMESPACES).text if device_id.find('SerialNumber', NAMESPACES) is not None else '',
            }
        
        # Extract Event codes
        event_struct = method.find('.//Event', NAMESPACES)
        if event_struct is not None:
            params['events'] = []
            for event in event_struct.findall('.//EventStruct', NAMESPACES):
                event_code = event.find('EventCode', NAMESPACES)
                if event_code is not None:
                    params['events'].append(event_code.text)
        
        # Extract Parameters
        param_list = method.find('.//ParameterList', NAMESPACES)
        if param_list is not None:
            params['parameters'] = {}
            for param in param_list.findall('.//ParameterValueStruct', NAMESPACES):
                name = param.find('Name', NAMESPACES)
                value = param.find('Value', NAMESPACES)
                if name is not None and value is not None:
                    params['parameters'][name.text] = value.text
        
        return params
    
    def _parse_transfer_complete(self, method: ET.Element) -> Dict[str, Any]:
        """Parse TransferComplete message"""
        return {'status': 'complete'}
    
    def _parse_rpc_methods_response(self, method: ET.Element) -> Dict[str, Any]:
        """Parse GetRPCMethodsResponse"""
        methods = []
        method_list = method.find('.//MethodList', NAMESPACES)
        if method_list is not None:
            for m in method_list.findall('string', NAMESPACES):
                if m.text:
                    methods.append(m.text)
        return {'methods': methods}
    
    def create_inform_response(self) -> str:
        """Create InformResponse SOAP message"""
        envelope = ET.Element('{http://schemas.xmlsoap.org/soap/envelope/}Envelope')
        envelope.set('xmlns:soap', NAMESPACES['soap'])
        envelope.set('xmlns:cwmp', NAMESPACES['cwmp'])
        
        body = ET.SubElement(envelope, '{http://schemas.xmlsoap.org/soap/envelope/}Body')
        inform_response = ET.SubElement(body, '{urn:dslforum-org:cwmp-1-0}InformResponse')
        max_envelopes = ET.SubElement(inform_response, 'MaxEnvelopes')
        max_envelopes.text = '1'
        
        return self._prettify_xml(envelope)
    
    def create_get_parameter_values(self, parameter_names: list) -> str:
        """Create GetParameterValues request"""
        envelope = ET.Element('{http://schemas.xmlsoap.org/soap/envelope/}Envelope')
        envelope.set('xmlns:soap', NAMESPACES['soap'])
        envelope.set('xmlns:cwmp', NAMESPACES['cwmp'])
        
        # Add SOAP Header with ID
        header = ET.SubElement(envelope, '{http://schemas.xmlsoap.org/soap/envelope/}Header')
        cwmp_id = ET.SubElement(header, '{urn:dslforum-org:cwmp-1-0}ID')
        cwmp_id.set('soap:mustUnderstand', '1')
        cwmp_id.text = str(uuid.uuid4())
        
        body = ET.SubElement(envelope, '{http://schemas.xmlsoap.org/soap/envelope/}Body')
        get_params = ET.SubElement(body, '{urn:dslforum-org:cwmp-1-0}GetParameterValues')
        
        param_names = ET.SubElement(get_params, 'ParameterNames')
        param_names.set('soap:arrayType', f'xsd:string[{len(parameter_names)}]')
        
        for name in parameter_names:
            string = ET.SubElement(param_names, 'string')
            string.text = name
        
        return self._prettify_xml(envelope)
    
    def create_set_parameter_values(self, parameters: Dict[str, str]) -> str:
        """Create SetParameterValues request"""
        envelope = ET.Element('{http://schemas.xmlsoap.org/soap/envelope/}Envelope')
        envelope.set('xmlns:soap', NAMESPACES['soap'])
        envelope.set('xmlns:cwmp', NAMESPACES['cwmp'])
        
        # Add SOAP Header with ID
        header = ET.SubElement(envelope, '{http://schemas.xmlsoap.org/soap/envelope/}Header')
        cwmp_id = ET.SubElement(header, '{urn:dslforum-org:cwmp-1-0}ID')
        cwmp_id.set('soap:mustUnderstand', '1')
        cwmp_id.text = str(uuid.uuid4())
        
        body = ET.SubElement(envelope, '{http://schemas.xmlsoap.org/soap/envelope/}Body')
        set_params = ET.SubElement(body, '{urn:dslforum-org:cwmp-1-0}SetParameterValues')
        
        param_list = ET.SubElement(set_params, 'ParameterList')
        param_list.set('soap:arrayType', f'cwmp:ParameterValueStruct[{len(parameters)}]')
        
        for name, value in parameters.items():
            param_struct = ET.SubElement(param_list, 'ParameterValueStruct')
            name_elem = ET.SubElement(param_struct, 'Name')
            name_elem.text = name
            value_elem = ET.SubElement(param_struct, 'Value')
            value_elem.set('xsi:type', 'xsd:string')
            value_elem.text = str(value)
        
        param_key = ET.SubElement(set_params, 'ParameterKey')
        param_key.text = ''
        
        return self._prettify_xml(envelope)
    
    def create_reboot(self) -> str:
        """Create Reboot request"""
        envelope = ET.Element('{http://schemas.xmlsoap.org/soap/envelope/}Envelope')
        envelope.set('xmlns:soap', NAMESPACES['soap'])
        envelope.set('xmlns:cwmp', NAMESPACES['cwmp'])
        
        # Add SOAP Header with ID
        header = ET.SubElement(envelope, '{http://schemas.xmlsoap.org/soap/envelope/}Header')
        cwmp_id = ET.SubElement(header, '{urn:dslforum-org:cwmp-1-0}ID')
        cwmp_id.set('soap:mustUnderstand', '1')
        cwmp_id.text = str(uuid.uuid4())
        
        body = ET.SubElement(envelope, '{http://schemas.xmlsoap.org/soap/envelope/}Body')
        reboot = ET.SubElement(body, '{urn:dslforum-org:cwmp-1-0}Reboot')
        command_key = ET.SubElement(reboot, 'CommandKey')
        command_key.text = f'reboot_{datetime.utcnow().timestamp()}'
        
        return self._prettify_xml(envelope)
    
    def create_factory_reset(self) -> str:
        """Create FactoryReset request"""
        envelope = ET.Element('{http://schemas.xmlsoap.org/soap/envelope/}Envelope')
        envelope.set('xmlns:soap', NAMESPACES['soap'])
        envelope.set('xmlns:cwmp', NAMESPACES['cwmp'])
        
        # Add SOAP Header with ID
        header = ET.SubElement(envelope, '{http://schemas.xmlsoap.org/soap/envelope/}Header')
        cwmp_id = ET.SubElement(header, '{urn:dslforum-org:cwmp-1-0}ID')
        cwmp_id.set('soap:mustUnderstand', '1')
        cwmp_id.text = str(uuid.uuid4())
        
        body = ET.SubElement(envelope, '{http://schemas.xmlsoap.org/soap/envelope/}Body')
        factory_reset = ET.SubElement(body, '{urn:dslforum-org:cwmp-1-0}FactoryReset')
        
        return self._prettify_xml(envelope)
    
    def create_empty_response(self) -> str:
        """Create empty SOAP response (no more commands)"""
        envelope = ET.Element('{http://schemas.xmlsoap.org/soap/envelope/}Envelope')
        envelope.set('xmlns:soap', NAMESPACES['soap'])
        
        body = ET.SubElement(envelope, '{http://schemas.xmlsoap.org/soap/envelope/}Body')
        
        return self._prettify_xml(envelope)
    
    def _prettify_xml(self, elem: ET.Element) -> str:
        """Convert XML element to pretty string"""
        from xml.dom import minidom
        rough_string = ET.tostring(elem, encoding='unicode')
        reparsed = minidom.parseString(rough_string)
        return reparsed.toprettyxml(indent="  ", encoding='utf-8').decode('utf-8')


# Global CWMP server instance
cwmp_server = CWMPServer()
