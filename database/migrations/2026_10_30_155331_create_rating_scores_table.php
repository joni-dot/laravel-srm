<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rating_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_rating_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rating_criterion_id')->constrained('rating_criteria')->restrictOnDelete();
            $table->unsignedTinyInteger('score');

            $table->unique(['supplier_rating_id', 'rating_criterion_id']);
            $table->index('rating_criterion_id');
        });

        DB::statement('ALTER TABLE rating_scores ADD CONSTRAINT rating_scores_score_check CHECK (score BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_scores');
    }
};
