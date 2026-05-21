<?php

return [
    'path' => env('LOGGER_PATH', storage_path('logs/structured')),
    'retention_days' => env('LOGGER_RETENTION_DAYS', 30),
];
