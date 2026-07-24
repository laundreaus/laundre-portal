<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('maintenance_docs')) {
            Schema::create('maintenance_docs', function (Blueprint $t) {
                $t->id();
                $t->string('title');
                $t->string('category')->nullable();
                $t->string('machine')->nullable();
                $t->text('note')->nullable();
                $t->string('file_path')->nullable();
                $t->string('file_name')->nullable();
                $t->unsignedBigInteger('file_size')->nullable();
                $t->timestamps();
            });
        }
    }
    public function down(): void { Schema::dropIfExists('maintenance_docs'); }
};
