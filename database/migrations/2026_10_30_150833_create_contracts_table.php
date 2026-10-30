<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('contract_number', 50)->nullable()->unique();
            $table->foreignId('contract_type_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('auto_renews')->default(false);
            $table->unsignedSmallInteger('renewal_term_months')->nullable();
            $table->unsignedSmallInteger('notice_period_days')->nullable();
            $table->decimal('value', 15, 2)->nullable();
            $table->char('currency_code', 3)->nullable();
            $table->string('value_period', 10)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // The day someone must act: the last day to cancel an auto-renewing contract, otherwise the end date.
            $table->date('action_by')->nullable()->storedAs(
                'CASE WHEN auto_renews THEN ends_on - COALESCE(notice_period_days, 0) ELSE ends_on END'
            );

            $table->index('supplier_id');
            $table->index('owner_user_id');
            $table->index('contract_type_id');
            $table->index(['status', 'action_by']);
        });

        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_status_check CHECK (status IN ('draft', 'active', 'terminated'))");
        DB::statement("ALTER TABLE contracts ADD CONSTRAINT contracts_value_period_check CHECK (value_period IN ('total', 'yearly', 'monthly'))");
        DB::statement('ALTER TABLE contracts ADD CONSTRAINT contracts_renewal_check CHECK (NOT auto_renews OR (ends_on IS NOT NULL AND renewal_term_months >= 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
