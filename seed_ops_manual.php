<?php
// Idempotent seeder: adds the Franchise Operations Manual as a protected (view-only) franchisee doc.
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\OnboardingDocument;
$fp = 'onboarding-seed/franchise-operations-manual.pdf';
if (!is_file(public_path($fp))) { echo "manual file not found at public/{$fp}\n"; exit(1); }
$d = OnboardingDocument::firstOrNew(['file_path'=>$fp]);
$d->title = 'Franchise Operations Manual';
$d->file_name = 'Franchise Operations Manual.pdf';
$d->mime = 'application/pdf';
$d->audience = 'franchisee';
$d->protected = true;
if (!$d->exists) { $d->position = 0; }
$d->save();
echo "Operations Manual seeded: id={$d->id}, protected=".($d->protected?'yes':'no').", audience={$d->audience}\n";
