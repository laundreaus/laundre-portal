<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// Site scoring (population, parking, size, footfall) folded into each prospect card.
return new class extends Migration {
    public function up(): void {
        Schema::table('site_prospects', function (Blueprint $t) {
            $t->json('scores')->nullable();               // {population, parking, size, footfall}
            $t->decimal('score_overall', 4, 1)->nullable();
        });
    }
    public function down(): void {
        Schema::table('site_prospects', function (Blueprint $t) {
            $t->dropColumn(['scores', 'score_overall']);
        });
    }
};
