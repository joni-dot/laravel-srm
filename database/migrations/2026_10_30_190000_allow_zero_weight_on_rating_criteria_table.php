<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * New criteria start at zero, so adding one can never push the total weight over the limit.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE rating_criteria DROP CONSTRAINT rating_criteria_weight_check');
        DB::statement('ALTER TABLE rating_criteria ADD CONSTRAINT rating_criteria_weight_check CHECK (weight BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE rating_criteria ALTER COLUMN weight SET DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('UPDATE rating_criteria SET weight = 1 WHERE weight = 0');
        DB::statement('ALTER TABLE rating_criteria DROP CONSTRAINT rating_criteria_weight_check');
        DB::statement('ALTER TABLE rating_criteria ADD CONSTRAINT rating_criteria_weight_check CHECK (weight BETWEEN 1 AND 100)');
        DB::statement('ALTER TABLE rating_criteria ALTER COLUMN weight SET DEFAULT 25');
    }
};
