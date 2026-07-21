<?php
return [
    'name' => env('APP_NAME', 'Laundré Portal'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => env('APP_TIMEZONE', 'Australia/Brisbane'),
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_AU',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [],
    'maintenance' => ['driver' => 'file'],
];
