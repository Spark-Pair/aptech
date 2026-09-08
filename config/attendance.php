<?php

return [
    'device_ip' => env('ZKTECO_IP', '192.168.100.125'),
    'device_port' => (int) env('ZKTECO_PORT', 4370),
    'device_timeout' => (int) env('ZKTECO_TIMEOUT', 5),
    'off_day' => 0, // Sunday; Carbon day-of-week numbering.
    'shift_start' => env('ATTENDANCE_SHIFT_START'), // Local time, HH:MM.
    'shift_end' => env('ATTENDANCE_SHIFT_END'), // Local time, HH:MM.
];
