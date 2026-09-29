<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('document_views')) return;
        Schema::create('document_views', function (Blueprint $t) {
            $t->id();
            $t->foreignId('onboarding_document_id')->constrained('onboarding_documents')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('viewed_at')->nullable();
            $t->timestamps();
            $t->index(['onboarding_document_id']);
            $t->index(['user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('document_views'); }
};
