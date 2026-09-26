<?php

return [
    'token_minutes' => (int) env('API_TOKEN_MINUTES', 1440),
    'photo_disk' => env('VEHICLE_PHOTO_DISK', 'vehicle_photos'),
    'stream_seconds' => (int) env('REALTIME_STREAM_SECONDS', 20),
    'pusher_enabled' => (bool) env('REALTIME_PUSHER_ENABLED', false),
];
