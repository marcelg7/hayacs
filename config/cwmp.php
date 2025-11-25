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
];
