<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// Admin-uploaded files per laundromat: lease, brochures, and insurance certificates of currency.
return new class extends Migration {
    public function up(): void {
        Schema::create('location_files', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('location_id');
            $t->string('category', 30)->default('other'); // lease | brochure | insurance | other
            $t->string('name');                             // display name / title
            $t->string('file_path');
            $t->string('file_name')->nullable();
            $t->unsignedBigInteger('size')->nullable();
            $t->date('expiry')->nullable();                 // insurance certificate expiry
            $t->string('uploaded_by')->nullable();
            $t->timestamps();
            $t->index(['location_id', 'category']);
        });
    }
    public function down(): void { Schema::dropIfExists('location_files'); }
};
