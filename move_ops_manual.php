<?php
// Move the Franchise Operations Manual OUT of the potential-franchisee onboarding
// library and INTO the general Documents module (as a protected view-only doc).
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\OnboardingDocument;
use App\Models\Document;

$fp = 'onboarding-seed/franchise-operations-manual.pdf';

// 1) remove from onboarding document library (cascades its document_views)
$removed = OnboardingDocument::where('file_path', $fp)->delete();

// 2) add/update in the general Documents module (idempotent), visible to all franchises
$doc = Document::firstOrNew(['file_path' => $fp]);
$doc->title = 'Franchise Operations Manual';
$doc->file_name = 'Franchise Operations Manual.pdf';
$doc->category = 'Operations Manual';
$doc->visibility = 'all';
$doc->protected = true;
$doc->link = '';
$doc->save();

echo "Onboarding rows removed: {$removed}. Documents id={$doc->id}, protected=".($doc->protected?'yes':'no').", visibility={$doc->visibility}\n";
