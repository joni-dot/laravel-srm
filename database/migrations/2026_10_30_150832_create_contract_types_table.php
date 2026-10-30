<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX contract_types_name_unique ON contract_types (lower(name))');

        // A starting list, so the contract form works right away. Admins can change it in the app.
        DB::table('contract_types')->insert(array_map(
            fn (string $name) => ['name' => $name, 'created_at' => now(), 'updated_at' => now()],
            ['Framework agreement', 'Purchase agreement', 'Service contract', 'Non-disclosure agreement', 'Other'],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_types');
    }
};
