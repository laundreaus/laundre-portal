<?php
// Set the franchisee + investor onboarding welcome video.
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Setting;

$url = 'https://youtu.be/afh7Zo32ZeE';
Setting::put('onboarding_video_franchisee', $url);
Setting::put('onboarding_video_investor', $url);
echo "Welcome video set for franchisee + investor: {$url}\n";
