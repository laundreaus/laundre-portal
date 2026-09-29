<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// Per-laundromat machines. Manual for now; will be populated from the Fagor systems backend.
return new class extends Migration {
    public function up(): void {
        Schema::create('machines', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('location_id');
            $t->string('name')->nullable();       // e.g. Washer 8
            $t->string('type', 40)->nullable();   // washer | dryer | dispenser | other
            $t->string('model')->nullable();      // Fagor model
            $t->string('serial')->nullable();
            $t->string('status', 40)->nullable(); // online | offline | maintenance
            $t->string('source', 20)->default('manual'); // manual | fagor
            $t->string('fagor_id')->nullable();
            $t->json('meta')->nullable();
            $t->timestamps();
            $t->index(['location_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('machines'); }
};
