<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('sales', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained()->cascadeOnDelete();
            $t->date('date');
            $t->decimal('revenue', 12, 2)->default(0);
            $t->unsignedInteger('txns')->default(0);
            $t->timestamps();
            $t->unique(['location_id', 'date']);
        });
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name'); $t->string('category')->nullable(); $t->string('contact')->nullable();
            $t->string('phone')->nullable(); $t->string('email')->nullable(); $t->string('website')->nullable();
            $t->text('notes')->nullable();
            $t->json('locations')->nullable(); // ['global'] | ['head-office', location_id, ...]
            $t->timestamps();
        });
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->string('title'); $t->string('category')->nullable();
            $t->string('visibility')->default('all'); // 'all' or location_id
            $t->string('file_path')->nullable(); $t->string('file_name')->nullable(); $t->string('link')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
        });
        Schema::create('guides', function (Blueprint $t) {
            $t->id();
            $t->string('title'); $t->string('category')->nullable();
            $t->string('visibility')->default('all');
            $t->string('file_path')->nullable(); $t->string('file_name')->nullable(); $t->string('link')->nullable();
            $t->string('note')->nullable();
            $t->timestamps();
        });
        Schema::create('tickets', function (Blueprint $t) {
            $t->id();
            $t->string('type')->default('Incident'); // Incident|Question
            $t->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('user_name')->nullable(); $t->string('user_email')->nullable();
            $t->string('subject'); $t->text('body');
            $t->string('status')->default('Open'); // Open|Closed
            $t->timestamps();
        });
        Schema::create('ticket_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $t->string('from'); $t->string('role')->nullable(); $t->text('text');
            $t->timestamps();
        });
        Schema::create('cleaning_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('by')->nullable(); $t->date('date');
            $t->json('items')->nullable(); $t->json('labels')->nullable();
            $t->text('notes')->nullable(); $t->text('issues')->nullable();
            $t->json('photos')->nullable();
            $t->timestamps();
            $t->unique(['location_id', 'date']);
        });
        Schema::create('maintenance_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('by')->nullable(); $t->date('date');
            $t->json('items')->nullable();
            $t->text('notes')->nullable(); $t->text('issues')->nullable();
            $t->json('photos')->nullable();
            $t->timestamps();
            $t->unique(['location_id', 'date']);
        });
        Schema::create('cost_projects', function (Blueprint $t) {
            $t->id();
            $t->string('name'); $t->string('location')->nullable();
            $t->unsignedInteger('sqm')->default(63);
            $t->decimal('margin_pct', 5, 2)->default(12.5);
            $t->decimal('gst_pct', 5, 2)->default(10);
            $t->json('items')->nullable();
            $t->timestamps();
        });
        Schema::create('site_scores', function (Blueprint $t) {
            $t->id();
            $t->string('name'); $t->string('address')->nullable(); $t->string('suburb')->nullable();
            $t->string('status')->default('Prospect');
            $t->unsignedInteger('sqm')->default(0); $t->unsignedInteger('rent')->default(0);
            $t->unsignedInteger('parking')->default(0); $t->unsignedInteger('pop')->default(0);
            $t->text('notes')->nullable();
            $t->json('scores')->nullable(); $t->decimal('overall', 4, 1)->default(0);
            $t->json('attachments')->nullable();
            $t->timestamps();
        });
        Schema::create('franchises', function (Blueprint $t) {
            $t->id();
            $t->string('name'); $t->string('location')->nullable(); $t->string('contact')->nullable();
            $t->foreignId('source_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $t->json('sections')->nullable();
            $t->timestamps();
        });
        Schema::create('bookkeeping_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('location_id')->constrained()->cascadeOnDelete();
            $t->string('fy'); // e.g. 2025
            $t->string('q1')->default('none'); $t->string('q2')->default('none');
            $t->string('q3')->default('none'); $t->string('q4')->default('none');
            $t->string('annual')->default('none');
            $t->json('dates')->nullable(); $t->json('files')->nullable();
            $t->timestamps();
            $t->unique(['location_id', 'fy']);
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->string('key')->primary(); $t->json('value')->nullable(); $t->timestamps();
        });
    }
    public function down(): void {
        foreach (['settings','bookkeeping_entries','franchises','site_scores','cost_projects','maintenance_logs','cleaning_logs','ticket_messages','tickets','guides','documents','suppliers','sales'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
