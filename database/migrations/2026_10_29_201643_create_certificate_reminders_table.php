<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('days_before');
            $table->date('remind_on');
            $table->timestamp('sent_at')->nullable();

            $table->unique(['certificate_id', 'days_before']);
        });

        // The daily reminder job only ever looks for unsent reminders, so index just those.
        DB::statement('CREATE INDEX certificate_reminders_unsent_index ON certificate_reminders (remind_on) WHERE sent_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_reminders');
    }
};
