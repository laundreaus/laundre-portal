<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// Contacts CRM. location_id NULL = global admin contact; set = a customer of that laundromat.
return new class extends Migration {
    public function up(): void {
        Schema::create('contacts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('location_id')->nullable();
            $t->string('name');
            $t->string('company')->nullable();
            $t->string('title')->nullable();                 // role / job title
            $t->string('email')->nullable();
            $t->string('phone')->nullable();
            $t->string('category', 40)->nullable();          // customer | agent | landlord | supplier | contractor | other
            $t->text('notes')->nullable();
            $t->string('source', 20)->default('manual');     // manual | bubblepay | gmail
            $t->string('external_id')->nullable();
            $t->timestamp('last_contact_at')->nullable();
            $t->string('created_by')->nullable();
            $t->timestamps();
            $t->index(['location_id', 'category']);
            $t->index('email');
        });
    }
    public function down(): void { Schema::dropIfExists('contacts'); }
};
