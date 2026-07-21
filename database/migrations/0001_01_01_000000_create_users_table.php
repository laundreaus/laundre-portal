<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('locations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('address')->nullable();
            $t->decimal('lat', 10, 6)->nullable();
            $t->decimal('lng', 10, 6)->nullable();
            $t->decimal('radius', 8, 2)->default(5);
            $t->string('unit', 4)->default('km');
            $t->string('status')->default('active');
            $t->date('date_approved')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('role')->default('franchisee'); // admin|franchisee|cleaner|maintenance
            $t->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $t->rememberToken();
            $t->timestamps();
        });
        Schema::create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token');
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('sessions', function (Blueprint $t) {
            $t->string('id')->primary();
            $t->foreignId('user_id')->nullable()->index();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->longText('payload');
            $t->integer('last_activity')->index();
        });
    }
    public function down(): void {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('locations');
    }
};
