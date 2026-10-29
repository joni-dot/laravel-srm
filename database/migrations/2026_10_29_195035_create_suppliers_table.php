<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('supplier_number', 50)->nullable()->unique();
            $table->string('status', 20)->default('prospect')->index();
            $table->char('country_code', 2);
            $table->string('business_id', 50)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->string('street_address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('payment_terms', 100)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->string('delivery_term', 3)->nullable();
            $table->string('delivery_place', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->text('search_text')->storedAs(
                "name || ' ' || coalesce(supplier_number, '') || ' ' || coalesce(business_id, '') || ' ' || coalesce(vat_number, '')"
            );

            $table->index('name');
            $table->unique(['country_code', 'business_id']);
        });

        DB::statement('CREATE INDEX suppliers_search_text_trgm_index ON suppliers USING gin (search_text gin_trgm_ops)');
        DB::statement("ALTER TABLE suppliers ADD CONSTRAINT suppliers_status_check CHECK (status IN ('prospect', 'active', 'inactive', 'blocked'))");
        DB::statement("ALTER TABLE suppliers ADD CONSTRAINT suppliers_delivery_term_check CHECK (delivery_term IN ('EXW', 'FCA', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP', 'FAS', 'FOB', 'CFR', 'CIF'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
