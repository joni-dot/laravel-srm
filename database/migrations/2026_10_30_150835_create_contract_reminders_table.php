<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('days_before');
            $table->date('remind_on');
            $table->timestamp('sent_at')->nullable();

            $table->unique(['contract_id', 'days_before']);
        });

        // The daily reminder job only ever looks for unsent reminders, so index just those.
        DB::statement('CREATE INDEX contract_reminders_unsent_index ON contract_reminders (remind_on) WHERE sent_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_reminders');
    }
};
