<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('maintenance_logs', 'geo')) {
            Schema::table('maintenance_logs', function (Blueprint $t) { $t->json('geo')->nullable()->after('photos'); });
        }
    }
    public function down(): void {
        if (Schema::hasColumn('maintenance_logs', 'geo')) {
            Schema::table('maintenance_logs', function (Blueprint $t) { $t->dropColumn('geo'); });
        }
    }
};
