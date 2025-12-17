@extends('docs.layout')

@section('docs-content')
<h1>Glossary</h1>

<p class="lead">
    Common terms and acronyms used in Hay ACS and TR-069 device management.
</p>

<div class="space-y-6">
    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">ACS</h3>
        <p class="text-gray-600 dark:text-gray-400">
            Auto Configuration Server. The management server that communicates with CPE devices
            using the TR-069 protocol. Hay ACS is an ACS implementation.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">BOOTSTRAP</h3>
        <p class="text-gray-600 dark:text-gray-400">
            A TR-069 event indicating the device has been factory reset or is connecting for
            the first time. Triggers automatic configuration restoration.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">Connection Request</h3>
        <p class="text-gray-600 dark:text-gray-400">
            An HTTP request from the ACS to the CPE asking it to establish a TR-069 session.
            Used to push tasks to devices without waiting for periodic inform.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">CPE</h3>
        <p class="text-gray-600 dark:text-gray-400">
            Customer Premises Equipment. Any device at the customer's location, such as
            routers, gateways, mesh nodes, and modems.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">CWMP</h3>
        <p class="text-gray-600 dark:text-gray-400">
            CPE WAN Management Protocol. The protocol defined by TR-069 for remote device
            management using SOAP over HTTP(S).
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">Data Model</h3>
        <p class="text-gray-600 dark:text-gray-400">
            The hierarchical structure of parameters on a device. TR-098 uses
            <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">InternetGatewayDevice.</code>,
            TR-181 uses <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">Device.</code>
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">Firmware</h3>
        <p class="text-gray-600 dark:text-gray-400">
            The software/operating system running on the device. Updated via TR-069 Download RPC.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">GigaCenter</h3>
        <p class="text-gray-600 dark:text-gray-400">
            Calix brand name for their router/ONT product line (844E, 844G, 854G, 812G models).
            Fully managed via TR-069.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">GigaSpire</h3>
        <p class="text-gray-600 dark:text-gray-400">
            Calix brand name for their consumer router product line (GS4220E, GS2020E).
            Uses TR-098 data model with some vendor extensions.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">Inform</h3>
        <p class="text-gray-600 dark:text-gray-400">
            The first message in a TR-069 session, sent by the CPE to the ACS. Contains
            device identification, event codes, and parameter values.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">Mesh AP</h3>
        <p class="text-gray-600 dark:text-gray-400">
            A satellite access point in a mesh WiFi system. Reports to a parent gateway
            and extends WiFi coverage. Has limited TR-069 parameters.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">OUI</h3>
        <p class="text-gray-600 dark:text-gray-400">
            Organizationally Unique Identifier. The first 6 characters of a MAC address,
            identifying the device manufacturer. Example: 80AB4D = Nokia.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">Parameter</h3>
        <p class="text-gray-600 dark:text-gray-400">
            A configurable or readable value on a device, identified by a path like
            <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID</code>
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">Periodic Inform</h3>
        <p class="text-gray-600 dark:text-gray-400">
            Scheduled TR-069 sessions initiated by the CPE at regular intervals (e.g., every 10 minutes).
            Allows the ACS to push pending tasks and collect device status.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">Provisioning</h3>
        <p class="text-gray-600 dark:text-gray-400">
            Initial configuration of a device when it first connects. Sets ACS credentials,
            periodic inform interval, and other baseline parameters.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">RPC</h3>
        <p class="text-gray-600 dark:text-gray-400">
            Remote Procedure Call. TR-069 operations like GetParameterValues, SetParameterValues,
            Reboot, Download, etc.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">Serial Number</h3>
        <p class="text-gray-600 dark:text-gray-400">
            Unique identifier for a device, reported in the TR-069 Inform. Used as the
            primary key for device management.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">SOAP</h3>
        <p class="text-gray-600 dark:text-gray-400">
            Simple Object Access Protocol. The XML-based messaging format used by TR-069
            for communication between ACS and CPE.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">Task</h3>
        <p class="text-gray-600 dark:text-gray-400">
            A queued TR-069 operation waiting to be sent to a device. Tasks are created
            when users request actions and executed during the next TR-069 session.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">TOTP</h3>
        <p class="text-gray-600 dark:text-gray-400">
            Time-based One-Time Password. The 2FA method used by Hay ACS, compatible with
            Google Authenticator, Microsoft Authenticator, and similar apps.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">TR-069</h3>
        <p class="text-gray-600 dark:text-gray-400">
            Technical Report 069. The Broadband Forum specification defining the CWMP protocol
            for remote CPE management.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">TR-098</h3>
        <p class="text-gray-600 dark:text-gray-400">
            The original TR-069 data model using <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">InternetGatewayDevice.</code>
            as root. Used by most devices in this deployment.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">TR-181</h3>
        <p class="text-gray-600 dark:text-gray-400">
            The newer TR-069 data model using <code class="bg-gray-200 dark:bg-gray-700 px-1 rounded">Device.</code>
            as root. More structured but requires different parameter paths.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">TransferComplete</h3>
        <p class="text-gray-600 dark:text-gray-400">
            A TR-069 notification sent by the CPE after a Download or Upload operation
            completes. Reports success/failure and transfer statistics.
        </p>
    </div>

    <div>
        <h3 class="font-semibold text-gray-900 dark:text-white">Workflow</h3>
        <p class="text-gray-600 dark:text-gray-400">
            An automated sequence of tasks applied to a device group. Used for bulk operations
            like firmware upgrades or configuration changes.
        </p>
    </div>
</div>
@endsection
