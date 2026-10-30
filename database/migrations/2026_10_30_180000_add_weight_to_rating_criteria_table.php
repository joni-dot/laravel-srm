<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every criterion starts with the same weight, so existing overall scores stay correct.
     */
    public function up(): void
    {
        Schema::table('rating_criteria', function (Blueprint $table) {
            $table->unsignedSmallInteger('weight')->default(25);
        });

        DB::statement('ALTER TABLE rating_criteria ADD CONSTRAINT rating_criteria_weight_check CHECK (weight BETWEEN 1 AND 100)');
    }

    public function down(): void
    {
        Schema::table('rating_criteria', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
