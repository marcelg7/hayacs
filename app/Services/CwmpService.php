<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;

class CwmpService
{
    private const SOAP_ENV = 'http://schemas.xmlsoap.org/soap/envelope/';
    private const SOAP_ENC = 'http://schemas.xmlsoap.org/soap/encoding/';
    private const XSD = 'http://www.w3.org/2001/XMLSchema';
    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';
    private const CWMP = 'urn:dslforum-org:cwmp-1-0';

    /**
     * Parse incoming SOAP/XML message from CPE device
     */
    public function parseInform(string $xml): array
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        // Register namespaces
        $xpath->registerNamespace('soap', self::SOAP_ENV);
        $xpath->registerNamespace('cwmp', self::CWMP);

        $result = [
            'method' => null,
            'device_id' => null,
            'manufacturer' => null,
            'oui' => null,
            'product_class' => null,
            'serial_number' => null,
            'events' => [],
            'parameters' => [],
            'max_envelopes' => 1,
            'current_time' => null,
            'retry_count' => 0,
        ];

        // Get method name
        $methodNode = $xpath->query('//soap:Body/*')->item(0);
        if ($methodNode) {
            $result['method'] = $methodNode->localName;
        }

        if ($result['method'] === 'Inform') {
            // Extract DeviceId
            $deviceIdNode = $xpath->query('//cwmp:Inform/DeviceId')->item(0);
            if ($deviceIdNode) {
                $result['manufacturer'] = $xpath->query('./Manufacturer', $deviceIdNode)->item(0)?->nodeValue;
                $result['oui'] = $xpath->query('./OUI', $deviceIdNode)->item(0)?->nodeValue;
                $result['product_class'] = $xpath->query('./ProductClass', $deviceIdNode)->item(0)?->nodeValue;
                $result['serial_number'] = $xpath->query('./SerialNumber', $deviceIdNode)->item(0)?->nodeValue;

                // Create device ID
                $result['device_id'] = sprintf(
                    '%s-%s-%s',
                    $result['oui'] ?? 'UNKNOWN',
                    $result['product_class'] ?? 'Unknown',
                    $result['serial_number'] ?? 'UNKNOWN'
                );
            }

            // Extract Events
            $eventNodes = $xpath->query('//cwmp:Inform/Event/EventStruct');
            foreach ($eventNodes as $eventNode) {
                $eventCode = $xpath->query('./EventCode', $eventNode)->item(0)?->nodeValue;
                $commandKey = $xpath->query('./CommandKey', $eventNode)->item(0)?->nodeValue;
                $result['events'][] = [
                    'code' => $eventCode,
                    'command_key' => $commandKey,
                ];
            }

            // Extract ParameterList
            $paramNodes = $xpath->query('//cwmp:Inform/ParameterList/ParameterValueStruct');
            foreach ($paramNodes as $paramNode) {
                $name = $xpath->query('./Name', $paramNode)->item(0)?->nodeValue;
                $value = $xpath->query('./Value', $paramNode)->item(0)?->nodeValue;
                $type = $xpath->query('./Value', $paramNode)->item(0)?->getAttribute('xsi:type');

                if ($name) {
                    $result['parameters'][$name] = [
                        'value' => $value,
                        'type' => $type,
                    ];
                }
            }

            // Extract other fields
            $result['max_envelopes'] = (int) ($xpath->query('//cwmp:Inform/MaxEnvelopes')->item(0)?->nodeValue ?? 1);
            $result['current_time'] = $xpath->query('//cwmp:Inform/CurrentTime')->item(0)?->nodeValue;
            $result['retry_count'] = (int) ($xpath->query('//cwmp:Inform/RetryCount')->item(0)?->nodeValue ?? 0);
        }

        return $result;
    }

    /**
     * Parse RPC response from CPE device
     */
    public function parseResponse(string $xml): array
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        // Register namespaces (devices may use SOAP-ENV or soap prefix)
        $xpath->registerNamespace('soap', self::SOAP_ENV);
        $xpath->registerNamespace('soapenv', self::SOAP_ENV);
        $xpath->registerNamespace('cwmp', self::CWMP);

        $result = [
            'method' => null,
            'parameters' => [],
            'status' => null,
            'fault' => null,
        ];

        // Check for SOAP Fault (try both soap: and soapenv: prefixes)
        $faultNode = $xpath->query('//soap:Fault | //soapenv:Fault')->item(0);
        if ($faultNode) {
            $result['fault'] = [
                'faultcode' => $xpath->query('./faultcode', $faultNode)->item(0)?->nodeValue,
                'faultstring' => $xpath->query('./faultstring', $faultNode)->item(0)?->nodeValue,
            ];
            return $result;
        }

        // Get method name (try both soap: and soapenv: prefixes)
        $methodNode = $xpath->query('//soap:Body/* | //soapenv:Body/*')->item(0);
        if ($methodNode) {
            $result['method'] = str_replace('Response', '', $methodNode->localName);
        }

        // Parse GetParameterValuesResponse
        if ($result['method'] === 'GetParameterValues') {
            // Query without namespace prefix on ParameterValueStruct (some devices don't use it)
            $paramNodes = $xpath->query('//cwmp:GetParameterValuesResponse//*[local-name()="ParameterValueStruct"]');
            foreach ($paramNodes as $paramNode) {
                $name = $xpath->query('.//*[local-name()="Name"]', $paramNode)->item(0)?->nodeValue;
                $value = $xpath->query('.//*[local-name()="Value"]', $paramNode)->item(0)?->nodeValue;
                $type = $xpath->query('.//*[local-name()="Value"]', $paramNode)->item(0)?->getAttribute('xsi:type');

                if ($name) {
                    $result['parameters'][$name] = [
                        'value' => $value,
                        'type' => $type,
                    ];
                }
            }
        }

        // Parse SetParameterValuesResponse
        if ($result['method'] === 'SetParameterValues') {
            // Use local-name() for compatibility with devices that don't use namespace prefixes
            $statusNode = $xpath->query('//cwmp:SetParameterValuesResponse/*[local-name()="Status"]')->item(0);
            $result['status'] = (int) ($statusNode?->nodeValue ?? 0);
        }

        // Parse GetParameterNamesResponse
        if ($result['method'] === 'GetParameterNames') {
            $result['parameter_list'] = [];
            // Query without namespace prefix for compatibility
            $paramNodes = $xpath->query('//cwmp:GetParameterNamesResponse//*[local-name()="ParameterInfoStruct"]');
            foreach ($paramNodes as $paramNode) {
                $name = $xpath->query('.//*[local-name()="Name"]', $paramNode)->item(0)?->nodeValue;
                $writable = $xpath->query('.//*[local-name()="Writable"]', $paramNode)->item(0)?->nodeValue;

                if ($name) {
                    $result['parameter_list'][] = [
                        'name' => $name,
                        'writable' => $writable === '1' || $writable === 'true',
                    ];
                }
            }
        }

        // Parse TransferComplete
        if ($result['method'] === 'TransferComplete') {
            $result['command_key'] = $xpath->query('//cwmp:TransferComplete/CommandKey')->item(0)?->nodeValue;
            $result['fault_code'] = (int) ($xpath->query('//cwmp:TransferComplete/FaultStruct/FaultCode')->item(0)?->nodeValue ?? 0);
            $result['fault_string'] = $xpath->query('//cwmp:TransferComplete/FaultStruct/FaultString')->item(0)?->nodeValue ?? '';
            $result['start_time'] = $xpath->query('//cwmp:TransferComplete/StartTime')->item(0)?->nodeValue;
            $result['complete_time'] = $xpath->query('//cwmp:TransferComplete/CompleteTime')->item(0)?->nodeValue;
        }

        return $result;
    }

    /**
     * Create InformResponse
     */
    public function createInformResponse(int $maxEnvelopes = 1): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $envelope = $dom->createElementNS(self::SOAP_ENV, 'soap:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:soap', self::SOAP_ENV);
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsd', self::XSD);
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::XSI);
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cwmp', self::CWMP);
        $dom->appendChild($envelope);

        // Create SOAP Body
        $body = $dom->createElement('soap:Body');
        $envelope->appendChild($body);

        // Create InformResponse
        $informResponse = $dom->createElement('cwmp:InformResponse');
        $body->appendChild($informResponse);

        $maxEnvelopesEl = $dom->createElement('MaxEnvelopes', (string) $maxEnvelopes);
        $informResponse->appendChild($maxEnvelopesEl);

        return $dom->saveXML();
    }

    /**
     * Create GetParameterValues RPC
     */
    public function createGetParameterValues(array $parameterNames): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $envelope = $this->createSoapEnvelope($dom);
        $body = $envelope->getElementsByTagNameNS(self::SOAP_ENV, 'Body')->item(0);

        // Create GetParameterValues
        $getParams = $dom->createElement('cwmp:GetParameterValues');
        $body->appendChild($getParams);

        // Create ParameterNames array
        $paramNamesArray = $dom->createElement('ParameterNames');
        $paramNamesArray->setAttribute('soapenc:arrayType', 'xsd:string[' . count($parameterNames) . ']');
        $getParams->appendChild($paramNamesArray);

        foreach ($parameterNames as $name) {
            $paramName = $dom->createElement('string', htmlspecialchars($name));
            $paramNamesArray->appendChild($paramName);
        }

        return $dom->saveXML();
    }

    /**
     * Create GetParameterNames RPC
     * Used to discover all available parameters on a device
     *
     * @param string $path Parameter path (e.g., "InternetGatewayDevice." or "Device.")
     * @param bool $nextLevel If true, only get immediate children. If false, get all recursively
     */
    public function createGetParameterNames(string $path, bool $nextLevel = false): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $envelope = $this->createSoapEnvelope($dom);
        $body = $envelope->getElementsByTagNameNS(self::SOAP_ENV, 'Body')->item(0);

        // Create GetParameterNames
        $getParamNames = $dom->createElement('cwmp:GetParameterNames');
        $body->appendChild($getParamNames);

        // Add ParameterPath
        $paramPath = $dom->createElement('ParameterPath', htmlspecialchars($path));
        $getParamNames->appendChild($paramPath);

        // Add NextLevel (boolean)
        $nextLevelElement = $dom->createElement('NextLevel', $nextLevel ? 'true' : 'false');
        $getParamNames->appendChild($nextLevelElement);

        return $dom->saveXML();
    }

    /**
     * Create SetParameterValues RPC
     *
     * @param array $parameters Array of parameters in format:
     *   Simple: ['ParamName' => 'value']
     *   Enhanced: ['ParamName' => ['value' => '123', 'type' => 'xsd:unsignedInt']]
     */
    public function createSetParameterValues(array $parameters): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $envelope = $this->createSoapEnvelope($dom);
        $body = $envelope->getElementsByTagNameNS(self::SOAP_ENV, 'Body')->item(0);

        // Create SetParameterValues
        $setParams = $dom->createElement('cwmp:SetParameterValues');
        $body->appendChild($setParams);

        // Create ParameterList
        $paramList = $dom->createElement('ParameterList');
        $paramList->setAttribute('soapenc:arrayType', 'cwmp:ParameterValueStruct[' . count($parameters) . ']');
        $setParams->appendChild($paramList);

        foreach ($parameters as $name => $value) {
            $paramStruct = $dom->createElement('ParameterValueStruct');
            $paramList->appendChild($paramStruct);

            $nameEl = $dom->createElement('Name', htmlspecialchars($name));
            $paramStruct->appendChild($nameEl);

            // Support both simple string values and enhanced array format
            if (is_array($value)) {
                $paramValue = $value['value'] ?? '';
                $paramType = $value['type'] ?? 'xsd:string';
            } else {
                $paramValue = $value;
                $paramType = 'xsd:string';
            }

            $valueEl = $dom->createElement('Value', htmlspecialchars((string) $paramValue));
            $valueEl->setAttribute('xsi:type', $paramType);
            $paramStruct->appendChild($valueEl);
        }

        // Add ParameterKey (empty string is fine)
        $paramKey = $dom->createElement('ParameterKey', '');
        $setParams->appendChild($paramKey);

        return $dom->saveXML();
    }

    /**
     * Create Reboot RPC
     */
    public function createReboot(string $commandKey = ''): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $envelope = $this->createSoapEnvelope($dom);
        $body = $envelope->getElementsByTagNameNS(self::SOAP_ENV, 'Body')->item(0);

        // Create Reboot
        $reboot = $dom->createElement('cwmp:Reboot');
        $body->appendChild($reboot);

        $cmdKey = $dom->createElement('CommandKey', htmlspecialchars($commandKey));
        $reboot->appendChild($cmdKey);

        return $dom->saveXML();
    }

    /**
     * Create FactoryReset RPC
     */
    public function createFactoryReset(): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $envelope = $this->createSoapEnvelope($dom);
        $body = $envelope->getElementsByTagNameNS(self::SOAP_ENV, 'Body')->item(0);

        // Create FactoryReset
        $factoryReset = $dom->createElement('cwmp:FactoryReset');
        $body->appendChild($factoryReset);

        return $dom->saveXML();
    }

    /**
     * Create Download RPC (for firmware upgrades, config files, etc.)
     */
    public function createDownload(string $url, string $fileType = '1 Firmware Upgrade Image', string $username = '', string $password = ''): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $envelope = $this->createSoapEnvelope($dom);
        $body = $envelope->getElementsByTagNameNS(self::SOAP_ENV, 'Body')->item(0);

        // Create Download
        $download = $dom->createElement('cwmp:Download');
        $body->appendChild($download);

        $commandKey = $dom->createElement('CommandKey', 'download_' . time());
        $download->appendChild($commandKey);

        $fileTypeEl = $dom->createElement('FileType', htmlspecialchars($fileType));
        $download->appendChild($fileTypeEl);

        $urlEl = $dom->createElement('URL', htmlspecialchars($url));
        $download->appendChild($urlEl);

        $usernameEl = $dom->createElement('Username', htmlspecialchars($username));
        $download->appendChild($usernameEl);

        $passwordEl = $dom->createElement('Password', htmlspecialchars($password));
        $download->appendChild($passwordEl);

        $fileSize = $dom->createElement('FileSize', '0');
        $download->appendChild($fileSize);

        $targetFileName = $dom->createElement('TargetFileName', '');
        $download->appendChild($targetFileName);

        $delaySeconds = $dom->createElement('DelaySeconds', '0');
        $download->appendChild($delaySeconds);

        $successUrl = $dom->createElement('SuccessURL', '');
        $download->appendChild($successUrl);

        $failureUrl = $dom->createElement('FailureURL', '');
        $download->appendChild($failureUrl);

        return $dom->saveXML();
    }

    /**
     * Create Upload RPC (for log files, config backups, etc.)
     */
    public function createUpload(string $url, string $fileType = '3 Vendor Log File', string $username = '', string $password = ''): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $envelope = $this->createSoapEnvelope($dom);
        $body = $envelope->getElementsByTagNameNS(self::SOAP_ENV, 'Body')->item(0);

        // Create Upload
        $upload = $dom->createElement('cwmp:Upload');
        $body->appendChild($upload);

        $commandKey = $dom->createElement('CommandKey', 'upload_' . time());
        $upload->appendChild($commandKey);

        $fileTypeEl = $dom->createElement('FileType', htmlspecialchars($fileType));
        $upload->appendChild($fileTypeEl);

        $urlEl = $dom->createElement('URL', htmlspecialchars($url));
        $upload->appendChild($urlEl);

        $usernameEl = $dom->createElement('Username', htmlspecialchars($username));
        $upload->appendChild($usernameEl);

        $passwordEl = $dom->createElement('Password', htmlspecialchars($password));
        $upload->appendChild($passwordEl);

        $delaySeconds = $dom->createElement('DelaySeconds', '0');
        $upload->appendChild($delaySeconds);

        return $dom->saveXML();
    }

    /**
     * Create empty response (ends session)
     */
    public function createEmptyResponse(): string
    {
        return '';
    }

    /**
     * Helper: Create SOAP Envelope structure
     */
    private function createSoapEnvelope(DOMDocument $dom, bool $includeId = false): DOMElement
    {
        $envelope = $dom->createElementNS(self::SOAP_ENV, 'soap:Envelope');
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:soap', self::SOAP_ENV);
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:soapenc', self::SOAP_ENC);
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsd', self::XSD);
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::XSI);
        $envelope->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cwmp', self::CWMP);
        $dom->appendChild($envelope);

        // Create SOAP Header (optionally with cwmp:ID)
        // Note: cwmp:ID disabled by default as some devices (e.g., Calix 844E) reject it
        if ($includeId) {
            $header = $dom->createElementNS(self::SOAP_ENV, 'soap:Header');
            $envelope->appendChild($header);

            // Add cwmp:ID element (required for CPE to correlate requests/responses)
            $id = $dom->createElement('cwmp:ID', '1');
            $id->setAttribute('soap:mustUnderstand', '1');
            $header->appendChild($id);
        }

        // Create SOAP Body
        $body = $dom->createElementNS(self::SOAP_ENV, 'soap:Body');
        $envelope->appendChild($body);

        return $envelope;
    }
}
