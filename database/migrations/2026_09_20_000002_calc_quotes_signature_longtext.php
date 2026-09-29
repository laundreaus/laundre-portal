<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('calc_quotes')) return;
        Schema::table('calc_quotes', function (Blueprint $t) {
            $t->longText('signature')->nullable()->change(); // holds the drawn-signature PNG data URL
        });
    }
    public function down(): void {
        if (!Schema::hasTable('calc_quotes')) return;
        Schema::table('calc_quotes', function (Blueprint $t) {
            $t->text('signature')->nullable()->change();
        });
    }
};
