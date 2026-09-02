<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// About panel details + per-laundromat module on/off toggles.
return new class extends Migration {
    public function up(): void {
        Schema::table('locations', function (Blueprint $t) {
            $t->json('about')->nullable();    // business_name, abn, incorporated_on, lease_start, lease_length,
                                              // outgoings, rent, incentive, info, parking, centre/agent/cleaner contacts, key_contacts[]
            $t->json('modules')->nullable();  // { dashboard:true, profit:true, ... } — null = all on
        });
    }
    public function down(): void {
        Schema::table('locations', function (Blueprint $t) {
            $t->dropColumn(['about', 'modules']);
        });
    }
};
