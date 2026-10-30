<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_spends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('amount', 15, 2);
            $table->char('currency_code', 3);
            $table->timestamps();

            $table->unique(['supplier_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_spends');
    }
};
