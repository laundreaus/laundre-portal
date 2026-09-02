<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// Per-laundromat monthly expenses (manual entry now; Xero sync later — each laundromat = its own Xero org).
return new class extends Migration {
    public function up(): void {
        Schema::create('expenses', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('location_id');
            $t->string('month', 7);            // YYYY-MM
            $t->string('category')->nullable();
            $t->decimal('amount', 12, 2)->default(0);
            $t->string('source', 20)->default('manual'); // manual | xero
            $t->string('xero_id')->nullable();
            $t->string('note', 500)->nullable();
            $t->timestamps();
            $t->index(['location_id', 'month']);
        });
    }
    public function down(): void { Schema::dropIfExists('expenses'); }
};
