<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('locations', function (Blueprint $t) {
            if (!Schema::hasColumn('locations', 'keys_date'))    $t->date('keys_date')->nullable()->after('date_approved');
            if (!Schema::hasColumn('locations', 'opening_date')) $t->date('opening_date')->nullable()->after('keys_date');
        });
    }
    public function down(): void {
        Schema::table('locations', function (Blueprint $t) {
            if (Schema::hasColumn('locations', 'keys_date'))    $t->dropColumn('keys_date');
            if (Schema::hasColumn('locations', 'opening_date')) $t->dropColumn('opening_date');
        });
    }
};
