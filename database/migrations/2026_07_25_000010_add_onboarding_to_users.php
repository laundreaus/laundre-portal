<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users','phone'))        $t->string('phone')->nullable()->after('email');
            if (!Schema::hasColumn('users','invite_token')) $t->string('invite_token',80)->nullable()->unique()->after('remember_token');
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users','phone'))        $t->dropColumn('phone');
            if (Schema::hasColumn('users','invite_token')) $t->dropColumn('invite_token');
        });
    }
};
