#!/usr/bin/env php
<?php

/**
 * TR-069 Device Simulator
 *
 * Simulates a TR-069 CPE device connecting to the ACS
 * Usage: php simulate-device.php [options]
 */

class TR069DeviceSimulator
{
    private string $acsUrl;
    private string $acsUsername;
    private string $acsPassword;
    private string $manufacturer;
    private string $oui;
    private string $productClass;
    private string $serialNumber;
    private string $deviceId;
    private string $softwareVersion;
    private string $hardwareVersion;
    private bool $useTR181;

    public function __construct(array $options = [])
    {
        $this->acsUrl = $options['acs_url'] ?? 'http://localhost:8000/cwmp';
        $this->acsUsername = $options['acs_username'] ?? 'acs-user';
        $this->acsPassword = $options['acs_password'] ?? 'acs-password';
        $this->manufacturer = $options['manufacturer'] ?? 'SimulatedVendor';
        $this->oui = $options['oui'] ?? 'ABCDEF';
        $this->productClass = $options['product_class'] ?? 'TestRouter';
        $this->serialNumber = $options['serial_number'] ?? 'TEST' . rand(100000, 999999);
        $this->softwareVersion = $options['software_version'] ?? '1.0.0';
        $this->hardwareVersion = $options['hardware_version'] ?? 'v1';
        $this->useTR181 = $options['use_tr181'] ?? false;

        $this->deviceId = sprintf('%s-%s-%s', $this->oui, $this->productClass, $this->serialNumber);
    }

    public function sendInform(array $events = ['2 PERIODIC']): void
    {
        echo "Sending Inform to {$this->acsUrl}...\n";
        echo "Device ID: {$this->deviceId}\n";
        echo "Data Model: " . ($this->useTR181 ? 'TR-181' : 'TR-098') . "\n\n";

        $xml = $this->createInformMessage($events);

        $response = $this->sendSoapRequest($xml);

        if ($response) {
            echo "✓ Inform sent successfully!\n";
            echo "ACS Response received:\n";
            echo substr($response, 0, 500) . "...\n\n";

            $this->handleAcsResponse($response);
        } else {
            echo "✗ Failed to send Inform\n";
        }
    }

    private function createInformMessage(array $events): string
    {
        $eventStructs = '';
        foreach ($events as $event) {
            $eventStructs .= "<EventStruct>
                <EventCode>{$event}</EventCode>
                <CommandKey></CommandKey>
            </EventStruct>";
        }

        // Get parameter names based on data model
        if ($this->useTR181) {
            $swVersionParam = 'Device.DeviceInfo.SoftwareVersion';
            $hwVersionParam = 'Device.DeviceInfo.HardwareVersion';
            $manufParam = 'Device.DeviceInfo.Manufacturer';
            $modelParam = 'Device.DeviceInfo.ModelName';
            $connReqParam = 'Device.ManagementServer.ConnectionRequestURL';
            $ipParam = 'Device.IP.Interface.1.IPv4Address.1.IPAddress';
        } else {
            $swVersionParam = 'InternetGatewayDevice.DeviceInfo.SoftwareVersion';
            $hwVersionParam = 'InternetGatewayDevice.DeviceInfo.HardwareVersion';
            $manufParam = 'InternetGatewayDevice.DeviceInfo.Manufacturer';
            $modelParam = 'InternetGatewayDevice.DeviceInfo.ModelName';
            $connReqParam = 'InternetGatewayDevice.ManagementServer.ConnectionRequestURL';
            $ipParam = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress';
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:cwmp="urn:dslforum-org:cwmp-1-0">
    <soap:Header/>
    <soap:Body>
        <cwmp:Inform>
            <DeviceId>
                <Manufacturer>{$this->manufacturer}</Manufacturer>
                <OUI>{$this->oui}</OUI>
                <ProductClass>{$this->productClass}</ProductClass>
                <SerialNumber>{$this->serialNumber}</SerialNumber>
            </DeviceId>
            <Event soap:arrayType="cwmp:EventStruct[" . count($events) . "]">
                {$eventStructs}
            </Event>
            <MaxEnvelopes>1</MaxEnvelopes>
            <CurrentTime>2025-11-12T12:00:00Z</CurrentTime>
            <RetryCount>0</RetryCount>
            <ParameterList soap:arrayType="cwmp:ParameterValueStruct[6]">
                <ParameterValueStruct>
                    <Name>{$swVersionParam}</Name>
                    <Value xsi:type="xsd:string">{$this->softwareVersion}</Value>
                </ParameterValueStruct>
                <ParameterValueStruct>
                    <Name>{$hwVersionParam}</Name>
                    <Value xsi:type="xsd:string">{$this->hardwareVersion}</Value>
                </ParameterValueStruct>
                <ParameterValueStruct>
                    <Name>{$manufParam}</Name>
                    <Value xsi:type="xsd:string">{$this->manufacturer}</Value>
                </ParameterValueStruct>
                <ParameterValueStruct>
                    <Name>{$modelParam}</Name>
                    <Value xsi:type="xsd:string">{$this->productClass}</Value>
                </ParameterValueStruct>
                <ParameterValueStruct>
                    <Name>{$connReqParam}</Name>
                    <Value xsi:type="xsd:string">http://192.168.1.1:7547/ConnectionRequest</Value>
                </ParameterValueStruct>
                <ParameterValueStruct>
                    <Name>{$ipParam}</Name>
                    <Value xsi:type="xsd:string">203.0.113.42</Value>
                </ParameterValueStruct>
            </ParameterList>
        </cwmp:Inform>
    </soap:Body>
</soap:Envelope>
XML;
    }

    private function sendSoapRequest(string $xml): ?string
    {
        $ch = curl_init($this->acsUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ""',
            ],
            CURLOPT_USERPWD => "{$this->acsUsername}:{$this->acsPassword}",
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return $response;
        }

        echo "HTTP Error: {$httpCode}\n";
        return null;
    }

    private function handleAcsResponse(string $response): void
    {
        if (empty($response)) {
            echo "Session ended by ACS (empty response)\n";
            return;
        }

        // Check if it's InformResponse
        if (str_contains($response, 'InformResponse')) {
            echo "✓ InformResponse received - No pending tasks\n";
            return;
        }

        // Check for GetParameterValues
        if (str_contains($response, 'GetParameterValues')) {
            echo "⚠ ACS requested GetParameterValues\n";
            echo "  (In a real device, we would extract parameters and send response)\n";
            return;
        }

        // Check for SetParameterValues
        if (str_contains($response, 'SetParameterValues')) {
            echo "⚠ ACS requested SetParameterValues\n";
            echo "  (In a real device, we would set parameters and send response)\n";
            return;
        }

        // Check for Reboot
        if (str_contains($response, 'Reboot')) {
            echo "⚠ ACS requested Reboot\n";
            echo "  (In a real device, we would reboot)\n";
            return;
        }

        echo "Received unknown RPC method\n";
    }
}

// Parse command line arguments
$options = [
    'acs_url' => 'http://localhost:8000/cwmp',
    'manufacturer' => 'SimulatedVendor',
    'oui' => 'ABCDEF',
    'product_class' => 'TestRouter',
    'serial_number' => 'TEST' . rand(100000, 999999),
    'software_version' => '1.0.0',
    'hardware_version' => 'v1',
    'use_tr181' => false,
];

// Parse arguments
for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--url' && isset($argv[$i + 1])) {
        $options['acs_url'] = $argv[++$i];
    } elseif ($argv[$i] === '--manufacturer' && isset($argv[$i + 1])) {
        $options['manufacturer'] = $argv[++$i];
    } elseif ($argv[$i] === '--model' && isset($argv[$i + 1])) {
        $options['product_class'] = $argv[++$i];
    } elseif ($argv[$i] === '--serial' && isset($argv[$i + 1])) {
        $options['serial_number'] = $argv[++$i];
    } elseif ($argv[$i] === '--tr181') {
        $options['use_tr181'] = true;
    } elseif ($argv[$i] === '--help' || $argv[$i] === '-h') {
        echo <<<HELP
TR-069 Device Simulator

Usage: php simulate-device.php [options]

Options:
  --url <url>           ACS URL (default: http://localhost:8000/cwmp)
  --manufacturer <name> Manufacturer name (default: SimulatedVendor)
  --model <name>        Product class/model (default: TestRouter)
  --serial <number>     Serial number (default: random)
  --tr181               Use TR-181 data model instead of TR-098
  --help, -h            Show this help message

Examples:
  # Simulate TR-098 device
  php simulate-device.php

  # Simulate TR-181 device
  php simulate-device.php --tr181

  # Custom manufacturer and model
  php simulate-device.php --manufacturer Acme --model HomeRouter5G

  # Connect to remote ACS
  php simulate-device.php --url http://acs.example.com/cwmp

HELP;
        exit(0);
    }
}

// Create and run simulator
echo "═══════════════════════════════════════════════════════════\n";
echo "  TR-069 Device Simulator\n";
echo "═══════════════════════════════════════════════════════════\n\n";

$simulator = new TR069DeviceSimulator($options);
$simulator->sendInform(['2 PERIODIC', '1 BOOT']);

echo "\n═══════════════════════════════════════════════════════════\n";
echo "  Simulation Complete\n";
echo "═══════════════════════════════════════════════════════════\n";
