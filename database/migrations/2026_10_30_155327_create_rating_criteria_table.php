<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX rating_criteria_name_unique ON rating_criteria (lower(name))');

        // A starting list, so suppliers can be rated right away. Admins can change it in the app.
        DB::table('rating_criteria')->insert(array_map(
            fn (string $name) => ['name' => $name, 'created_at' => now(), 'updated_at' => now()],
            ['Quality', 'Delivery', 'Price', 'Service'],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_criteria');
    }
};
