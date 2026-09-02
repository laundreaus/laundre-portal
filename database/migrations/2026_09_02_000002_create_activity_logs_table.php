<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('actor_name')->nullable();
            $t->string('actor_role', 40)->nullable();
            $t->string('action', 60)->nullable();   // view / create / update / delete / auth / deploy / system
            $t->string('subject', 500)->nullable();
            $t->string('method', 10)->nullable();
            $t->string('path', 500)->nullable();
            $t->string('ip', 60)->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['created_at']);
            $t->index(['user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('activity_logs'); }
};
