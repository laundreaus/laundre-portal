<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('pipeline_cards')) return;
        Schema::table('pipeline_cards', function (Blueprint $t) {
            if (!Schema::hasColumn('pipeline_cards','type'))            $t->string('type')->nullable();          // user type tag: franchisee / investor / general
            if (!Schema::hasColumn('pipeline_cards','source'))          $t->string('source')->nullable();        // lead source: wordpress / manual / import
            if (!Schema::hasColumn('pipeline_cards','stage_changed_at')) $t->timestamp('stage_changed_at')->nullable(); // when the card last changed column (drives the 14-day follow-up)
            if (!Schema::hasColumn('pipeline_cards','automation'))       $t->json('automation')->nullable();      // flags: {nda_email_sent, meeting_email_sent}
        });
    }
    public function down(): void {
        Schema::table('pipeline_cards', function (Blueprint $t) {
            foreach (['type','source','stage_changed_at','automation'] as $c) {
                if (Schema::hasColumn('pipeline_cards',$c)) $t->dropColumn($c);
            }
        });
    }
};
