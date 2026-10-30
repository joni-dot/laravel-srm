<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('rated_on');
            $table->decimal('overall_score', 3, 2);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'rated_on', 'id']);
            $table->index('rated_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ratings');
    }
};
