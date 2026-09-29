<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('enquiries')) return;
        Schema::create('enquiries', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('city')->nullable();
            $t->text('message')->nullable();
            $t->string('type')->default('general');   // franchisee / investor / general
            $t->string('source')->default('wordpress'); // wordpress / manual
            $t->string('status')->default('new');      // new / approved / dismissed
            $t->foreignId('card_id')->nullable();      // the CRM card created on approval
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('enquiries'); }
};
