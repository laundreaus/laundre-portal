<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('pipeline_cards')) return;
        Schema::create('pipeline_cards', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('contact')->nullable();
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('city')->nullable();
            $t->text('notes')->nullable();
            $t->string('stage')->default('nda_sent');
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->integer('position')->default(0);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('pipeline_cards'); }
};
