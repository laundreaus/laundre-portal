<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('calc_quotes')) return;
        Schema::create('calc_quotes', function (Blueprint $t) {
            $t->id();
            $t->string('token', 64)->unique();
            $t->string('name')->nullable();
            $t->integer('sqm')->nullable();
            $t->string('reference')->nullable();
            $t->string('client_name')->nullable();
            $t->string('client_email')->nullable();
            $t->json('payload')->nullable();     // structured figures (fit-out, machines, rent, bank, totals)
            $t->longText('html')->nullable();     // rendered client quote document (the "PDF")
            $t->decimal('total', 12, 2)->default(0);
            $t->string('status')->default('sent'); // sent | approved
            $t->string('signer_name')->nullable();
            $t->text('signature')->nullable();
            $t->timestamp('signed_at')->nullable();
            $t->string('signed_ip')->nullable();
            $t->string('created_by')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('calc_quotes'); }
};
