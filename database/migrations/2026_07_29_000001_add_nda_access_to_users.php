<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users','nda_signed_at'))   $t->timestamp('nda_signed_at')->nullable();
            if (!Schema::hasColumn('users','nda_signer_name'))  $t->string('nda_signer_name')->nullable();
            if (!Schema::hasColumn('users','nda_signature'))    $t->longText('nda_signature')->nullable();
            if (!Schema::hasColumn('users','nda_address'))      $t->string('nda_address')->nullable();
            if (!Schema::hasColumn('users','access_expires_at')) $t->timestamp('access_expires_at')->nullable();
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            foreach (['nda_signed_at','nda_signer_name','nda_signature','nda_address','access_expires_at'] as $c) {
                if (Schema::hasColumn('users',$c)) $t->dropColumn($c);
            }
        });
    }
};
