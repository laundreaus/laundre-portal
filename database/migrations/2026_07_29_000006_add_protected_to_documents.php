<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('documents', function (Blueprint $t) {
            if (!Schema::hasColumn('documents','protected')) $t->boolean('protected')->default(false);
        });
    }
    public function down(): void {
        Schema::table('documents', function (Blueprint $t) {
            if (Schema::hasColumn('documents','protected')) $t->dropColumn('protected');
        });
    }
};
