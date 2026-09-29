<?php
return [
    'postmark' => ['token' => env('POSTMARK_TOKEN')],
    'ses' => ['key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY'), 'region' => env('AWS_DEFAULT_REGION', 'us-east-1')],

    // Twilio SMS for admin notifications. Add TWILIO_SID / TWILIO_TOKEN / TWILIO_FROM to .env.
    // Recipient mobile numbers are managed in the Notifications settings (Setting: admin_sms_numbers).
    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from'  => env('TWILIO_FROM'),
    ],
];
