<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// One Xero connection per laundromat (each laundromat is its own Xero organisation).
// Tokens are filled once OAuth is wired; the base is here so expense sync can be set up.
return new class extends Migration {
    public function up(): void {
        Schema::create('xero_connections', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('location_id')->unique();
            $t->string('org_name')->nullable();
            $t->string('tenant_id')->nullable();
            $t->string('status', 20)->default('disconnected'); // disconnected | pending | connected
            $t->text('access_token')->nullable();
            $t->text('refresh_token')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('last_sync_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('xero_connections'); }
};
