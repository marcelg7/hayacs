<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CWMP Authentication Credentials
    |--------------------------------------------------------------------------
    |
    | Multiple credential pairs for device authentication to the ACS.
    | Supports migration scenarios where devices use different credentials.
    |
    */

    'credentials' => [
        // Primary credentials (default)
        [
            'username' => env('CWMP_USERNAME', 'acs-user'),
            'password' => env('CWMP_PASSWORD', 'acs-password'),
        ],
        // Secondary credentials (USS migration)
        [
            'username' => env('CWMP_USERNAME_2'),
            'password' => env('CWMP_PASSWORD_2'),
        ],
        // Third credentials (optional)
        [
            'username' => env('CWMP_USERNAME_3'),
            'password' => env('CWMP_PASSWORD_3'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Connection Request Credentials
    |--------------------------------------------------------------------------
    |
    | Credentials set on devices for ACS-initiated connection requests.
    | These are pushed to devices during auto-provisioning.
    |
    */

    'connection_request_username' => env('CWMP_CR_USERNAME', 'admin'),
    'connection_request_password' => env('CWMP_CR_PASSWORD', 'admin'),
];
