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
    private const CWMP_1_0 = 'urn:dslforum-org:cwmp-1-0';
    private const CWMP_1_1 = 'urn:dslforum-org:cwmp-1-1';
    private const CWMP_1_2 = 'urn:dslforum-org:cwmp-1-2';

    // Default to CWMP 1.0 for backward compatibility
    private const CWMP = 'urn:dslforum-org:cwmp-1-0';

    // Session state - stores cwmp:ID and namespace from device's message
    private ?string $sessionCwmpId = null;
    private ?string $sessionCwmpNamespace = null;

    // Current device context for namespace fallback
    private ?\App\Models\Device $currentDevice = null;

    /**
     * Extract session info (cwmp:ID and CWMP namespace) from incoming XML
     * This should be called before parseInform/parseResponse to capture session state
     */
    public function extractSessionInfo(string $xml): void
    {
        $dom = new DOMDocument();
        @$dom->loadXML($xml);

        // Find the CWMP namespace used by the device
        $root = $dom->documentElement;
        if ($root) {
            // Check for cwmp namespace declaration
            foreach (['cwmp', 'CWMP'] as $prefix) {
                $ns = $root->lookupNamespaceUri($prefix);
                if ($ns && str_starts_with($ns, 'urn:dslforum-org:cwmp-1-')) {
                    $this->sessionCwmpNamespace = $ns;
                    break;
                }
            }

            // Also check attributes for namespace
            if (!$this->sessionCwmpNamespace) {
                foreach ($root->attributes as $attr) {
                    if (str_starts_with($attr->nodeValue, 'urn:dslforum-org:cwmp-1-')) {
                        $this->sessionCwmpNamespace = $attr->nodeValue;
                        break;
                    }
                }
            }
        }

        // Extract cwmp:ID from SOAP Header
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('soap', self::SOAP_ENV);
        $xpath->registerNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');

        // Try different namespace prefixes for Header and ID
        $idQueries = [
            '//*[local-name()="Header"]/*[local-name()="ID"]',
            '//soap:Header//*[local-name()="ID"]',
            '//soapenv:Header//*[local-name()="ID"]',
        ];

        foreach ($idQueries as $query) {
            $idNode = $xpath->query($query)->item(0);
            if ($idNode && $idNode->nodeValue) {
                $this->sessionCwmpId = $idNode->nodeValue;
                break;
            }
        }
    }

    /**
     * Set the current device context for namespace fallback
     * This should be called before creating RPC requests when device is known
     */
    public function setDeviceContext(?\App\Models\Device $device): void
    {
        $this->currentDevice = $device;
    }

    /**
     * Get the CWMP namespace to use for responses
     * Priority: 1. Session namespace (from device's message)
     *           2. Device-based default (based on device type)
     *           3. Default CWMP 1.0
     *
     * CRITICAL: Device families use different CWMP versions:
     * - Calix GigaCenters (844E, 844G, 854G, 804Mesh) - TR-098, CWMP 1.0
     * - Calix GigaSpires (GS4220E, GM1028, etc.) - TR-098, but require CWMP 1.2!
     * - Nokia devices - CWMP 1.2
     */
    public function getCwmpNamespace(): string
    {
        // First, use session namespace if available (from device's Inform)
        if ($this->sessionCwmpNamespace) {
            return $this->sessionCwmpNamespace;
        }

        // Second, check device context and use appropriate default
        if ($this->currentDevice) {
            // CRITICAL: GigaSpires use TR-098 data model but require CWMP 1.2
            // This is different from GigaCenters which use CWMP 1.0
            if ($this->currentDevice->isGigaSpire()) {
                return self::CWMP_1_2;
            }
            // GigaCenters (844E, 844G, 854G, 804Mesh) use CWMP 1.0
            if ($this->currentDevice->isGigaCenter()) {
                return self::CWMP;
            }
            // Other Calix devices: check data model
            if ($this->currentDevice->isCalix()) {
                $dataModel = $this->currentDevice->getDataModel();
                return $dataModel === 'TR-181' ? self::CWMP_1_2 : self::CWMP;
            }
            // Nokia TR-098 devices (OUI 80AB4D) use CWMP 1.0 (verified from USS traces)
            // Nokia TR-181 devices (OUI 0C7C28) use CWMP 1.2
            if ($this->currentDevice->isNokia()) {
                $oui = strtoupper($this->currentDevice->oui ?? '');
                // TR-098 Nokia (80AB4D) uses CWMP 1.0
                if (str_starts_with($oui, '80AB4D')) {
                    return self::CWMP;
                }
                // TR-181 Nokia (0C7C28) uses CWMP 1.2
                return self::CWMP_1_2;
            }
        }

        // Default to CWMP 1.0 for backward compatibility
        return self::CWMP;
    }

    /**
     * Get the cwmp:ID to echo back in responses
     */
    public function getSessionCwmpId(): ?string
    {
        return $this->sessionCwmpId;
    }

    /**
     * Set session info directly (used when restoring from session storage)
     */
    public function setSessionInfo(?string $cwmpId, ?string $cwmpNamespace): void
    {
        $this->sessionCwmpId = $cwmpId;
        $this->sessionCwmpNamespace = $cwmpNamespace;
    }

    /**
     * Generate a new unique ID for ACS-initiated requests
     * Uses incrementing counter format like USS does
     */
    private static int $acsIdCounter = 0;

    public function generateAcsId(): string
    {
        if (self::$acsIdCounter === 0) {
            // Initialize with a random starting point
            self::$acsIdCounter = random_int(100000, 999999);
        }
        return (string) self::$acsIdCounter++;
    }

    /**
     * Parse incoming SOAP/XML message from CPE device
     */
    public function parseInform(string $xml): array
    {
        // Extract session info before parsing
        $this->extractSessionInfo($xml);

        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $xpath = new DOMXPath($dom);

        // Register namespaces - register both CWMP 1.0 and the device's namespace
        $xpath->registerNamespace('soap', self::SOAP_ENV);
        $xpath->registerNamespace('cwmp', $this->getCwmpNamespace());
        // Also register cwmp10/11/12 for explicit version queries if needed
        $xpath->registerNamespace('cwmp10', self::CWMP_1_0);
        $xpath->registerNamespace('cwmp11', self::CWMP_1_1);
        $xpath->registerNamespace('cwmp12', self::CWMP_1_2);

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
            // Try with namespace first, then without (some devices don't namespace child elements)
            $result['command_key'] = $xpath->query('//cwmp:TransferComplete/CommandKey')->item(0)?->nodeValue
                ?? $xpath->query('//*[local-name()="TransferComplete"]/*[local-name()="CommandKey"]')->item(0)?->nodeValue;

            // FaultStruct children often don't have namespace prefix
            $result['fault_code'] = (int) ($xpath->query('//*[local-name()="TransferComplete"]//*[local-name()="FaultCode"]')->item(0)?->nodeValue ?? 0);
            $result['fault_string'] = $xpath->query('//*[local-name()="TransferComplete"]//*[local-name()="FaultString"]')->item(0)?->nodeValue ?? '';

            $result['start_time'] = $xpath->query('//cwmp:TransferComplete/StartTime')->item(0)?->nodeValue
                ?? $xpath->query('//*[local-name()="TransferComplete"]/*[local-name()="StartTime"]')->item(0)?->nodeValue;
            $result['complete_time'] = $xpath->query('//cwmp:TransferComplete/CompleteTime')->item(0)?->nodeValue
                ?? $xpath->query('//*[local-name()="TransferComplete"]/*[local-name()="CompleteTime"]')->item(0)?->nodeValue;
        }

        // Parse AddObjectResponse
        if ($result['method'] === 'AddObject') {
            $instanceNode = $xpath->query('//cwmp:AddObjectResponse/*[local-name()="InstanceNumber"]')->item(0);
            $statusNode = $xpath->query('//cwmp:AddObjectResponse/*[local-name()="Status"]')->item(0);
            $result['instance_number'] = $instanceNode?->nodeValue;
            $result['status'] = (int) ($statusNode?->nodeValue ?? 1);
        }

        // Parse DeleteObjectResponse
        if ($result['method'] === 'DeleteObject') {
            $statusNode = $xpath->query('//cwmp:DeleteObjectResponse/*[local-name()="Status"]')->item(0);
            $result['status'] = (int) ($statusNode?->nodeValue ?? 1);
        }

        return $result;
    }

    /**
     * Create InformResponse
     * Echoes back the device's cwmp:ID from the Inform message (per TR-069 spec)
     */
    public function createInformResponse(int $maxEnvelopes = 1): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope with cwmp:ID echoed back (isResponse = true)
        $result = $this->createSoapEnvelope($dom, null, true);
        $body = $result['body'];

        // Create InformResponse
        $informResponse = $dom->createElement('cwmp:InformResponse');
        $body->appendChild($informResponse);

        $maxEnvelopesEl = $dom->createElement('MaxEnvelopes', (string) $maxEnvelopes);
        $informResponse->appendChild($maxEnvelopesEl);

        return $this->fixNamespaceOrder($dom->saveXML());
    }

    /**
     * Create GetParameterValues RPC
     */
    public function createGetParameterValues(array $parameterNames): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $result = $this->createSoapEnvelope($dom);
        $body = $result['body'];

        // Create GetParameterValues
        $getParams = $dom->createElement('cwmp:GetParameterValues');
        $body->appendChild($getParams);

        // Create ParameterNames array
        $paramNamesArray = $dom->createElement('ParameterNames');
        $paramNamesArray->setAttribute('soap-enc:arrayType', 'xsd:string[' . count($parameterNames) . ']');
        $getParams->appendChild($paramNamesArray);

        foreach ($parameterNames as $name) {
            $paramName = $dom->createElement('string', htmlspecialchars($name));
            $paramNamesArray->appendChild($paramName);
        }

        return $this->fixNamespaceOrder($dom->saveXML());
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
        $result = $this->createSoapEnvelope($dom);
        $body = $result['body'];

        // Create GetParameterNames
        $getParamNames = $dom->createElement('cwmp:GetParameterNames');
        $body->appendChild($getParamNames);

        // Add ParameterPath
        $paramPath = $dom->createElement('ParameterPath', htmlspecialchars($path));
        $getParamNames->appendChild($paramPath);

        // Add NextLevel (boolean)
        $nextLevelElement = $dom->createElement('NextLevel', $nextLevel ? 'true' : 'false');
        $getParamNames->appendChild($nextLevelElement);

        return $this->fixNamespaceOrder($dom->saveXML());
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
        $result = $this->createSoapEnvelope($dom);
        $body = $result['body'];

        // Create SetParameterValues
        $setParams = $dom->createElement('cwmp:SetParameterValues');
        $body->appendChild($setParams);

        // Create ParameterList
        $paramList = $dom->createElement('ParameterList');
        $paramList->setAttribute('soap-enc:arrayType', 'cwmp:ParameterValueStruct[' . count($parameters) . ']');
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

            // Handle boolean values properly - PHP false becomes "" when cast to string
            if ($paramType === 'xsd:boolean') {
                // Convert to "1" or "0" for TR-069 boolean
                if (is_bool($paramValue)) {
                    $paramValue = $paramValue ? '1' : '0';
                } elseif (is_string($paramValue)) {
                    // Normalize string boolean values
                    $paramValue = in_array(strtolower($paramValue), ['true', '1', 'yes']) ? '1' : '0';
                } else {
                    $paramValue = $paramValue ? '1' : '0';
                }
            }

            $valueEl = $dom->createElement('Value', htmlspecialchars((string) $paramValue));
            $valueEl->setAttribute('xsi:type', $paramType);
            $paramStruct->appendChild($valueEl);
        }

        // Add ParameterKey (empty string is fine)
        $paramKey = $dom->createElement('ParameterKey', '');
        $setParams->appendChild($paramKey);

        return $this->fixNamespaceOrder($dom->saveXML());
    }

    /**
     * Create Reboot RPC
     */
    public function createReboot(string $commandKey = ''): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $result = $this->createSoapEnvelope($dom);
        $body = $result['body'];

        // Create Reboot
        $reboot = $dom->createElement('cwmp:Reboot');
        $body->appendChild($reboot);

        $cmdKey = $dom->createElement('CommandKey', htmlspecialchars($commandKey));
        $reboot->appendChild($cmdKey);

        return $this->fixNamespaceOrder($dom->saveXML());
    }

    /**
     * Create FactoryReset RPC
     */
    public function createFactoryReset(): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $result = $this->createSoapEnvelope($dom);
        $body = $result['body'];

        // Create FactoryReset
        $factoryReset = $dom->createElement('cwmp:FactoryReset');
        $body->appendChild($factoryReset);

        return $this->fixNamespaceOrder($dom->saveXML());
    }

    /**
     * Create AddObject RPC (for creating new object instances like PortMapping)
     */
    public function createAddObject(string $objectName, string $parameterKey = ''): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $result = $this->createSoapEnvelope($dom);
        $body = $result['body'];

        // Create AddObject
        $addObject = $dom->createElement('cwmp:AddObject');
        $body->appendChild($addObject);

        $objectNameEl = $dom->createElement('ObjectName', htmlspecialchars($objectName));
        $addObject->appendChild($objectNameEl);

        $paramKeyEl = $dom->createElement('ParameterKey', htmlspecialchars($parameterKey));
        $addObject->appendChild($paramKeyEl);

        return $this->fixNamespaceOrder($dom->saveXML());
    }

    /**
     * Create DeleteObject RPC (for deleting object instances)
     */
    public function createDeleteObject(string $objectName, string $parameterKey = ''): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $result = $this->createSoapEnvelope($dom);
        $body = $result['body'];

        // Create DeleteObject
        $deleteObject = $dom->createElement('cwmp:DeleteObject');
        $body->appendChild($deleteObject);

        $objectNameEl = $dom->createElement('ObjectName', htmlspecialchars($objectName));
        $deleteObject->appendChild($objectNameEl);

        $paramKeyEl = $dom->createElement('ParameterKey', htmlspecialchars($parameterKey));
        $deleteObject->appendChild($paramKeyEl);

        return $this->fixNamespaceOrder($dom->saveXML());
    }

    /**
     * Create Download RPC (for firmware upgrades, config files, etc.)
     */
    public function createDownload(
        string $url,
        string $fileType = '1 Firmware Upgrade Image',
        string $username = '',
        string $password = '',
        ?int $taskId = null,
        int $fileSize = 0,
        string $targetFileName = ''
    ): string {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $result = $this->createSoapEnvelope($dom);
        $body = $result['body'];

        // Create Download
        $download = $dom->createElement('cwmp:Download');
        $body->appendChild($download);

        // Include task ID in command_key for correlation when TransferComplete arrives in a new session
        $commandKeyValue = $taskId ? "download_{$taskId}_" . time() : 'download_' . time();
        $commandKey = $dom->createElement('CommandKey', $commandKeyValue);
        $download->appendChild($commandKey);

        $fileTypeEl = $dom->createElement('FileType', htmlspecialchars($fileType));
        $download->appendChild($fileTypeEl);

        $urlEl = $dom->createElement('URL', htmlspecialchars($url));
        $download->appendChild($urlEl);

        $usernameEl = $dom->createElement('Username', htmlspecialchars($username));
        $download->appendChild($usernameEl);

        $passwordEl = $dom->createElement('Password', htmlspecialchars($password));
        $download->appendChild($passwordEl);

        $fileSizeEl = $dom->createElement('FileSize', (string) $fileSize);
        $download->appendChild($fileSizeEl);

        $targetFileNameEl = $dom->createElement('TargetFileName', htmlspecialchars($targetFileName));
        $download->appendChild($targetFileNameEl);

        $delaySeconds = $dom->createElement('DelaySeconds', '0');
        $download->appendChild($delaySeconds);

        $successUrl = $dom->createElement('SuccessURL', '');
        $download->appendChild($successUrl);

        $failureUrl = $dom->createElement('FailureURL', '');
        $download->appendChild($failureUrl);

        return $this->fixNamespaceOrder($dom->saveXML());
    }

    /**
     * Create Upload RPC (for log files, config backups, etc.)
     */
    public function createUpload(string $url, string $fileType = '3 Vendor Log File', string $username = '', string $password = '', ?int $taskId = null): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create SOAP Envelope
        $result = $this->createSoapEnvelope($dom);
        $body = $result['body'];

        // Create Upload
        $upload = $dom->createElement('cwmp:Upload');
        $body->appendChild($upload);

        // Include task ID in command_key for correlation when TransferComplete arrives in a new session
        $commandKeyValue = $taskId ? "upload_{$taskId}_" . time() : 'upload_' . time();
        $commandKey = $dom->createElement('CommandKey', $commandKeyValue);
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

        return $this->fixNamespaceOrder($dom->saveXML());
    }

    /**
     * Create empty response (ends session)
     */
    public function createEmptyResponse(): string
    {
        return '';
    }

    /**
     * Create TransferCompleteResponse to acknowledge device's TransferComplete message
     *
     * Per TR-069, when a device sends TransferComplete, the ACS must respond with
     * TransferCompleteResponse. If the device doesn't receive this acknowledgment,
     * it will keep retrying TransferComplete on every session until acknowledged.
     *
     * This is critical for GigaSpire and other devices that strictly follow TR-069.
     */
    public function createTransferCompleteResponse(): string
    {
        // Get the cwmp:ID to echo back
        $cwmpId = $this->sessionCwmpId ?: 'ACS_1';

        // Build response matching USS format
        $response = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope
  xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
  xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
  xmlns:cwmp="urn:dslforum-org:cwmp-1-0"
  xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <SOAP-ENV:Header>
    <cwmp:ID SOAP-ENV:mustUnderstand="1">{$cwmpId}</cwmp:ID>
  </SOAP-ENV:Header>
  <SOAP-ENV:Body>
    <cwmp:TransferCompleteResponse/>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

        return $response;
    }

    /**
     * Create GetRPCMethodsResponse for devices that request ACS capabilities
     * Per TR-069, the ACS must respond with the list of RPC methods it supports
     *
     * IMPORTANT: This response MUST use cwmp-1-0 namespace, not the device's namespace.
     * USS (NISC's ACS) uses cwmp-1-0 for GetRPCMethodsResponse regardless of device.
     * GigaSpire devices require this exact format or they wait 5 minutes before continuing.
     *
     * This method builds a USS-compatible response with matching SOAP prefix style.
     */
    public function createGetRPCMethodsResponse(): string
    {
        // Get the cwmp:ID to echo back
        $cwmpId = $this->sessionCwmpId ?: 'ACS_1';

        // List of all methods the ACS supports (matching USS's 22 methods)
        $methods = [
            'AddObject',
            'AutonomousDUStateChangeComplete',
            'AutonomousTransferComplete',
            'ChangeDUState',
            'DeleteObject',
            'Download',
            'DUStateChangeComplete',
            'FactoryReset',
            'GetParameterAttributes',
            'GetParameterNames',
            'GetParameterValues',
            'GetQueuedTransfers',
            'GetRPCMethods',
            'Inform',
            'Reboot',
            'RequestDownload',
            'ScheduleInform',
            'SetParameterAttributes',
            'SetParameterValues',
            'TransferComplete',
            'Upload',
            'X_000B23_DeleteQueuedTransfer',
        ];

        $methodCount = count($methods);

        // Build method list XML
        $methodListXml = '';
        foreach ($methods as $method) {
            $methodListXml .= "        <string>{$method}</string>\n";
        }

        // Build response matching USS format exactly:
        // - Uses SOAP-ENV: and SOAP-ENC: prefixes (uppercase)
        // - Uses cwmp-1-0 namespace (NOT cwmp-1-2)
        // - Uses SOAP-ENC:arrayType attribute
        $response = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope
  xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
  xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
  xmlns:cwmp="urn:dslforum-org:cwmp-1-0"
  xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <SOAP-ENV:Header>
    <cwmp:ID SOAP-ENV:mustUnderstand="1">{$cwmpId}</cwmp:ID>
  </SOAP-ENV:Header>
  <SOAP-ENV:Body>
    <cwmp:GetRPCMethodsResponse>
      <MethodList SOAP-ENC:arrayType="xsd:string[{$methodCount}]">
{$methodListXml}      </MethodList>
    </cwmp:GetRPCMethodsResponse>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

        return $response;
    }

    /**
     * Helper: Create SOAP Envelope structure
     *
     * @param DOMDocument $dom The DOM document
     * @param string|null $cwmpId The cwmp:ID to use. If null, generates a new one for ACS requests
     * @param bool $isResponse If true, echoes back the session cwmp:ID (for InformResponse, etc.)
     * @return array{envelope: DOMElement, body: DOMElement} Returns array with both envelope and body elements
     */
    private function createSoapEnvelope(DOMDocument $dom, ?string $cwmpId = null, bool $isResponse = false): array
    {
        // Use the device's CWMP namespace if available, otherwise default
        $cwmpNamespace = $this->getCwmpNamespace();

        // CRITICAL: USS uses specific namespace attribute order that GigaSpire devices require
        // Order: xmlns:cwmp, xmlns:soap-enc, xmlns:soap-env, xmlns:xsd, xmlns:xsi
        // PHP's DOMDocument puts the element's own namespace first, so we need a workaround
        // Create envelope as regular element, then manually set ALL namespaces in exact order
        $envelope = $dom->createElement('soap-env:Envelope');

        // Set namespaces in the exact order USS uses (critical for GigaSpire GS4220E)
        // Note: Using setAttribute instead of setAttributeNS to control order
        $envelope->setAttribute('xmlns:cwmp', $cwmpNamespace);
        $envelope->setAttribute('xmlns:soap-enc', self::SOAP_ENC);
        $envelope->setAttribute('xmlns:soap-env', self::SOAP_ENV);
        $envelope->setAttribute('xmlns:xsd', self::XSD);
        $envelope->setAttribute('xmlns:xsi', self::XSI);
        $dom->appendChild($envelope);

        // Determine the ID to use
        if ($isResponse && $this->sessionCwmpId) {
            // For responses, echo back the device's ID
            $idValue = $this->sessionCwmpId;
        } elseif ($cwmpId !== null) {
            // Use provided ID
            $idValue = $cwmpId;
        } else {
            // Generate a new ID for ACS-initiated requests
            $idValue = $this->generateAcsId();
        }

        // Always include SOAP Header with cwmp:ID (like USS does)
        // This is critical for GigaSpire devices to process commands
        $header = $dom->createElement('soap-env:Header');
        $envelope->appendChild($header);

        // Add cwmp:ID element with mustUnderstand="1" (lowercase soap-env)
        $id = $dom->createElement('cwmp:ID', $idValue);
        $id->setAttribute('soap-env:mustUnderstand', '1');
        $header->appendChild($id);

        // Create SOAP Body (lowercase soap-env)
        $body = $dom->createElement('soap-env:Body');
        $envelope->appendChild($body);

        // Return both envelope and body so callers can add content to body directly
        return ['envelope' => $envelope, 'body' => $body];
    }

    /**
     * Post-process XML to fix namespace attribute order
     * PHP's DOMDocument puts the element's own namespace first, but GigaSpire devices
     * require xmlns:cwmp to be first (matching USS behavior)
     *
     * Also ensures all required namespace declarations are present, even if PHP
     * optimizes them away.
     *
     * @param string $xml The XML string from saveXML()
     * @return string The XML with fixed namespace order
     */
    public function fixNamespaceOrder(string $xml): string
    {
        // Get the current CWMP namespace (1.0, 1.1, or 1.2)
        $cwmpNamespace = $this->getCwmpNamespace();

        // Match the soap-env:Envelope opening tag with its attributes
        // Pattern captures everything between <soap-env:Envelope and >
        $pattern = '/<soap-env:Envelope([^>]*)>/';

        return preg_replace_callback($pattern, function($matches) use ($cwmpNamespace) {
            $attrs = $matches[1];

            // Extract all namespace declarations that exist
            preg_match_all('/\s*(xmlns:[^=]+="[^"]*")/', $attrs, $nsMatches);

            $namespaces = [];
            if (!empty($nsMatches[1])) {
                foreach ($nsMatches[1] as $ns) {
                    // Extract prefix and value
                    if (preg_match('/xmlns:(\S+)="([^"]*)"/', $ns, $parts)) {
                        $namespaces[$parts[1]] = $parts[2];
                    }
                }
            }

            // Ensure all required namespaces are present (USS format)
            // This handles the case where PHP optimizes away "unused" namespace declarations
            $required = [
                'cwmp' => $cwmpNamespace,
                'soap-enc' => self::SOAP_ENC,
                'soap-env' => self::SOAP_ENV,
                'xsd' => self::XSD,
                'xsi' => self::XSI,
            ];

            foreach ($required as $prefix => $uri) {
                if (!isset($namespaces[$prefix])) {
                    $namespaces[$prefix] = $uri;
                }
            }

            // Build attributes in USS order: cwmp, soap-enc, soap-env, xsd, xsi
            $orderedNs = [];
            $order = ['cwmp', 'soap-enc', 'soap-env', 'xsd', 'xsi'];

            foreach ($order as $prefix) {
                if (isset($namespaces[$prefix])) {
                    $orderedNs[] = "xmlns:{$prefix}=\"{$namespaces[$prefix]}\"";
                    unset($namespaces[$prefix]);
                }
            }

            // Add any remaining namespaces (shouldn't be any, but just in case)
            foreach ($namespaces as $prefix => $uri) {
                $orderedNs[] = "xmlns:{$prefix}=\"{$uri}\"";
            }

            return '<soap-env:Envelope ' . implode(' ', $orderedNs) . '>';
        }, $xml);
    }
}
