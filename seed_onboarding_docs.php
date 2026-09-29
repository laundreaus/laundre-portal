<?php
// One-off idempotent seeder for the franchisee onboarding document library.
// Run from the app root:  php seed_onboarding_docs.php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\OnboardingDocument;

$DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
$XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
$PDF  = 'application/pdf';

$docs = [
    ['01-cover-letter.docx',                    'Cover Letter',                                   $DOCX],
    ['02-deed-of-prior-representations.docx',   'Deed of Prior Representations (Draft)',          $DOCX],
    ['03-disclosure-document.docx',             'Disclosure Document (Draft)',                    $DOCX],
    ['04-franchise-agreement.docx',             'Franchise Agreement (Draft)',                    $DOCX],
    ['05-signing-guide.docx',                   'Signing Guide',                                  $DOCX],
    ['06-projections-5yr.xlsx',                 'Projections — 5 Year Setup, P&L + Cashflow',     $XLSX],
    ['07-director-solvency-statement.pdf',      'Director Solvency Statement',                    $PDF],
    ['08-statutory-declaration.pdf',            'Statutory Declaration',                          $PDF],
    ['09-franchise-code-october-2025.pdf',      'Franchising Code of Conduct — October 2025',     $PDF],
    ['10-franchise-code-information-statement.pdf','Franchising Code — Information Statement 2025',$PDF],
];

$pos = 1; $added = 0;
foreach ($docs as [$file, $title, $mime]) {
    $fp = 'onboarding-seed/'.$file;
    if (!is_file(public_path($fp))) { continue; } // file not deployed yet — skip
    if (OnboardingDocument::where('file_path', $fp)->exists()) { $pos++; continue; }
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    OnboardingDocument::create([
        'title'=>$title, 'file_path'=>$fp,
        'file_name'=>preg_replace('/[^A-Za-z0-9 &.\-]/','',$title).'.'.$ext,
        'mime'=>$mime, 'audience'=>'franchisee', 'position'=>$pos++,
    ]);
    $added++;
}
echo "Onboarding docs seeded (added {$added}, total ".OnboardingDocument::count().")\n";
