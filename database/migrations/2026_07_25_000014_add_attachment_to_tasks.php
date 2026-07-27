<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('tasks', function (Blueprint $t) {
            if (!Schema::hasColumn('tasks','file_path')) $t->string('file_path')->nullable()->after('due_date');
            if (!Schema::hasColumn('tasks','file_name')) $t->string('file_name')->nullable()->after('file_path');
        });
    }
    public function down(): void {
        Schema::table('tasks', function (Blueprint $t) {
            if (Schema::hasColumn('tasks','file_path')) $t->dropColumn('file_path');
            if (Schema::hasColumn('tasks','file_name')) $t->dropColumn('file_name');
        });
    }
};
