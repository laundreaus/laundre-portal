<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// Soft-delete users so their signed NDA (and its PDF) is retained in the system after the account is removed.
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'deleted_at')) $t->softDeletes();
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'deleted_at')) $t->dropSoftDeletes();
        });
    }
};
