<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// Per-laundromat build roll-out milestones (program schedule) — visible to the franchisee.
return new class extends Migration {
    public function up(): void {
        Schema::create('milestones', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('location_id');
            $t->string('title');
            $t->text('detail')->nullable();
            $t->date('target_date')->nullable();
            $t->string('status', 20)->default('pending');    // pending | in_progress | done
            $t->integer('position')->default(0);
            $t->timestamps();
            $t->index(['location_id', 'position']);
        });
    }
    public function down(): void { Schema::dropIfExists('milestones'); }
};
