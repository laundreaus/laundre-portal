<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// Per-laundromat quotes: build -> share signing link -> franchisee signs -> Xero invoice schedule.
return new class extends Migration {
    public function up(): void {
        Schema::create('quotes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('location_id');
            $t->string('reference')->nullable();          // e.g. "Fit-out quote — Nerang"
            $t->string('client_name')->nullable();        // who signs (the franchisee)
            $t->string('client_email')->nullable();
            $t->text('notes')->nullable();
            $t->json('items')->nullable();                // [{description, amount}]
            $t->decimal('total', 12, 2)->default(0);
            $t->string('status', 20)->default('draft');   // draft | sent | signed | invoiced
            $t->string('token', 40)->unique();            // public signing link
            // signature
            $t->string('signer_name')->nullable();
            $t->text('signature')->nullable();            // typed / drawn signature data
            $t->timestamp('signed_at')->nullable();
            $t->string('signed_ip')->nullable();
            // Xero
            $t->json('invoice_schedule')->nullable();      // [{n, pct, amount, due, xero_status, xero_id}]
            $t->string('xero_status', 20)->nullable();     // null | pending | created
            $t->string('created_by')->nullable();
            $t->timestamps();
            $t->index(['location_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('quotes'); }
};
