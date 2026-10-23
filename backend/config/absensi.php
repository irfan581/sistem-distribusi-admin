<?php

return [
    'batas_jam'          => env('ABSENSI_BATAS_JAM', '08:00'),
    'timezone'           => env('APP_TIMEZONE', 'Asia/Jakarta'),
    'min_kunjungan'      => env('ABSENSI_MIN_KUNJUNGAN', 3),
    'token_expiry_hours' => env('ABSENSI_TOKEN_EXPIRY', 6),
];
