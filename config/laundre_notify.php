<?php
// Admin notification settings. The API key stays in .env; template IDs are not secret.
return [
    'sendgrid_key' => env('SENDGRID_API_KEY'),
    'from_email'   => env('LAUNDRE_FROM_EMAIL', 'luke@laundre.com.au'),
    'from_name'    => env('LAUNDRE_FROM_NAME', 'Laundré Dashboard'),
    // Admins who receive notifications (comma-separated in .env overrides this).
    'recipients'   => array_values(array_filter(array_map('trim', explode(',',
        env('ADMIN_NOTIFY_EMAIL', 'luke@laundre.com.au,adam@laundre.com.au'))))),
    // Extra monthly-report recipients who only receive ONE laundromat's figures.
    // Matched to a laundromat by name (case-insensitive "contains").
    'custom_monthly' => [
        ['email' => 'Melinda.Lynam@cbre.com', 'location' => 'Toowoomba', 'label' => 'CBRE — Centre Management'],
    ],
    // SendGrid dynamic template IDs, keyed by event.
    'templates' => [
        'laundromat_cleaned'    => 'd-dfe6bbae422746a181c0f6ab05202f07',
        'maintenance_completed' => 'd-9592a106310a4a9996d7b293f98edb8d',
        'monthly_sales_report'  => 'd-e5ca1d46b1b04158903eb7ae9b8ce620',
        'weekly_revenue'        => 'd-9e2e6c4f21474114ba4ee20a878e6119',
        'daily_tasks'           => 'd-070b325fb4d74096a0dbf2d361d40d6d',
        'nda_signed'            => 'd-32545e533c4f44e3b548ff3ca1ebc3cf',
        'accounts_uploaded'     => 'd-a9a0281e54c14168a08d747f93635432',
        'support_ticket'        => 'd-aa88f8cfa9bf4679bca66170440692eb',
    ],
    // Short heading shown at the top of each email.
    'headings' => [
        'laundromat_cleaned'    => 'A laundromat was cleaned',
        'maintenance_completed' => 'Maintenance was completed',
        'monthly_sales_report'  => 'Monthly sales report',
        'weekly_revenue'        => 'Weekly revenue',
        'daily_tasks'           => 'Your tasks for today',
        'nda_signed'            => 'An NDA was signed',
        'accounts_uploaded'     => 'BAS / accounts uploaded',
        'support_ticket'        => 'New support ticket',
    ],
];
