<?php

return [
    /*
    |--------------------------------------------------------------------------
    | XMPP Server Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the connection to the XMPP server (Prosody)
    | used for sending connection requests to Nokia Beacon devices.
    |
    */

    'enabled' => env('XMPP_ENABLED', false),

    'server' => env('XMPP_SERVER', 'hayacs.hay.net'),

    'port' => env('XMPP_PORT', 5222),

    'domain' => env('XMPP_DOMAIN', 'hayacs.hay.net'),

    'username' => env('XMPP_USERNAME', 'acs'),

    'password' => env('XMPP_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | TLS Settings
    |--------------------------------------------------------------------------
    |
    | Whether to use TLS encryption for the XMPP connection.
    | Recommended: true for production.
    |
    */

    'use_tls' => env('XMPP_USE_TLS', true),

    /*
    |--------------------------------------------------------------------------
    | Connection Request Settings
    |--------------------------------------------------------------------------
    |
    | Default credentials used in TR-069 XMPP connection request messages.
    | These should match the device's connection request credentials.
    |
    */

    'connection_request' => [
        'username' => env('CWMP_CONNECTION_REQUEST_USERNAME', 'admin'),
        'password' => env('CWMP_CONNECTION_REQUEST_PASSWORD', 'admin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | JabberID Format
    |--------------------------------------------------------------------------
    |
    | Format string for generating device JabberIDs.
    | Available placeholders: {serial_number}, {oui}, {product_class}
    |
    | USS format: ussprod_hay_{oui}-{product_class}-{serial_number}@nisc-uss.coop/xmpp
    | Hay ACS format: {serial_number}@{domain}/cwmp
    |
    */

    'jid_format' => env('XMPP_JID_FORMAT', '{serial_number}@{domain}/xmpp'),

    /*
    |--------------------------------------------------------------------------
    | Timeouts
    |--------------------------------------------------------------------------
    |
    | Connection and request timeouts in seconds.
    |
    */

    'connect_timeout' => env('XMPP_CONNECT_TIMEOUT', 10),

    'request_timeout' => env('XMPP_REQUEST_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Whether to log XMPP traffic for debugging.
    | Warning: Enabling this can create a lot of log data.
    |
    */

    'debug' => env('XMPP_DEBUG', false),
];
