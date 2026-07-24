<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('onboardings')) return;
        Schema::create('onboardings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('type')->default('franchisee'); // franchisee|investor
            $t->string('crm_stage')->default('invited'); // invited|onboarding|pending_approval|approved
            $t->timestamp('first_login_at')->nullable();
            $t->timestamp('nda_signed_at')->nullable();
            $t->timestamp('video_watched_at')->nullable();
            $t->timestamp('first_doc_opened_at')->nullable();
            $t->timestamp('contact_due_at')->nullable(); // nda_signed_at + 14 days
            // NDA signature capture
            $t->string('nda_name')->nullable();
            $t->string('nda_email')->nullable();
            $t->string('nda_phone')->nullable();
            $t->string('nda_address')->nullable();
            $t->string('nda_typed_name')->nullable();
            $t->longText('nda_signature')->nullable(); // drawn signature data URL
            $t->string('nda_ip',45)->nullable();
            // investor interest
            $t->decimal('interest_min',12,2)->nullable();
            $t->decimal('interest_max',12,2)->nullable();
            $t->text('interest_note')->nullable();
            $t->timestamp('interest_submitted_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('onboardings'); }
};
