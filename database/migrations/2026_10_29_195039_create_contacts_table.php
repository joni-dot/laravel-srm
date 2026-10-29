<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('job_title', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX contacts_one_primary_per_supplier ON contacts (supplier_id) WHERE is_primary');
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
