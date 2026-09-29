<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('users', 'investor_location_ids')) {
            Schema::table('users', function (Blueprint $table) {
                $table->json('investor_location_ids')->nullable()->after('location_id');
            });
        }
        // Existing "investor" accounts are prospects who have not been formally brought
        // on as investors yet — reclassify them as potential investors (they lose the
        // membership card until an admin upgrades them back to a full investor).
        DB::table('users')->where('role', 'investor')->update(['role' => 'potential_investor']);
    }

    public function down(): void {
        DB::table('users')->where('role', 'potential_investor')->update(['role' => 'investor']);
        if (Schema::hasColumn('users', 'investor_location_ids')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('investor_location_ids');
            });
        }
    }
};
