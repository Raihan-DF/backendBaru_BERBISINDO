<?php

// return [
//     'paths' => ['api/*', 'sanctum/csrf-cookie'],
//     'allowed_methods' => ['*'],
//     'allowed_origins' => [
//         'http://localhost:3000',
//         'http://127.0.0.1:3000',
//     ],
//     'allowed_origins_patterns' => [],
//     'allowed_headers' => ['*'],
//     'exposed_headers' => [],
//     'max_age' => 0,
//     'supports_credentials' => true,  // Ubah ke true untuk Sanctum
// ];

// return [
//     'paths' => ['api/*','storage/*', 'sanctum/csrf-cookie'],

//     'allowed_methods' => ['*'],

//     'allowed_origins' => [
//         'http://localhost:3000',
//         'http://127.0.0.1:3000',
//         'http://localhost:5173',
//         'http://127.0.0.1:5173',
//     ],

//     'allowed_origins_patterns' => [],

//     'allowed_headers' => ['*'],

//     'exposed_headers' => [
//         'Content-Range',
//         'Accept-Ranges',
//         'Content-Length',
//         'Content-Type'
//     ],

//     'max_age' => 0,

//     'supports_credentials' => true,
// ];

return [
    'paths' => [
        'api/*',
        'storage/*',
        'sanctum/csrf-cookie'
    ],

    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:3001',
        'http://127.0.0.1:3001',
        '',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [
        'Content-Range',
        'Accept-Ranges',
        'Content-Length',
        'Content-Type',
        'Content-Disposition'
    ],

    'max_age' => 0,

    'supports_credentials' => false,
];
