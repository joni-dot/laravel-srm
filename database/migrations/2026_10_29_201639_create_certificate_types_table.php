<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('is_required')->default(false);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX certificate_types_name_unique ON certificate_types (lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_types');
    }
};
