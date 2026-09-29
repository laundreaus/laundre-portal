<?php
// Backfill LDR-000000 member numbers for existing card-eligible users
// (investor / franchisee / user / admin). Idempotent.
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\User;

$assigned = 0;
foreach (User::whereNull('member_no')->orderBy('id')->get() as $u) {
    if ($u->hasCard()) { $u->assignMemberNo(); $assigned++; }
}
echo "Assigned {$assigned} new member numbers. Total members with a card: "
    . User::whereNotNull('member_no')->count() . "\n";
