<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('onboarding_documents')) return;
        Schema::create('onboarding_documents', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('file_path');          // e.g. /uploads/xxxx.pdf or onboarding-seed/xxx.pdf
            $t->string('file_name')->nullable();
            $t->string('mime')->nullable();
            $t->string('audience')->default('franchisee'); // franchisee|investor|both
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('onboarding_documents'); }
};
