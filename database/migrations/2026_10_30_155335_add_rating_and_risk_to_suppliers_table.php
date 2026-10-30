<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->decimal('latest_rating_score', 3, 2)->nullable();
            $table->string('risk_override', 10)->nullable();
            $table->text('risk_override_reason')->nullable();
            $table->foreignId('risk_overridden_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('risk_overridden_at')->nullable();

            $table->index('risk_overridden_by_user_id');
        });

        DB::statement("ALTER TABLE suppliers ADD CONSTRAINT suppliers_risk_override_check CHECK (risk_override IN ('low', 'medium', 'high'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE suppliers DROP CONSTRAINT suppliers_risk_override_check');

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('risk_overridden_by_user_id');
            $table->dropColumn(['latest_rating_score', 'risk_override', 'risk_override_reason', 'risk_overridden_at']);
        });
    }
};
