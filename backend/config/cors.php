<?php

$origins = array_filter(array_map('trim', explode(',', env(
    'CORS_ALLOWED_ORIGINS',
    'http://localhost:3000,http://localhost:5173'
))));

return [

    // broadcasting/auth ikut didaftarkan: endpoint otorisasi channel privat
    // Reverb ada di luar prefix api/, jadi tidak tercakup 'api/*'.
    // Di production CORS_PATHS diset kosong supaya Nginx yang menangani —
    // daftar ini otomatis tidak dipakai di sana.
    'paths' => strlen(env('CORS_PATHS', 'api/*')) > 0
        ? array_filter(array_map('trim', explode(',', env('CORS_PATHS', 'api/*,sanctum/csrf-cookie,broadcasting/auth'))))
        : [],

    'allowed_methods' => ['*'],

    'allowed_origins' => !empty($origins) ? $origins : ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,

];
