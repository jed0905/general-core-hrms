<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Interviews (many per application), their panelists (employees) and an
 * append-only reschedule log. starts_at/ends_at are DATETIME (never
 * auto-updated by MySQL/MariaDB); the lifecycle timestamps are nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->restrictOnDelete();
            $table->foreignId('interview_type_id')->constrained('interview_types')->restrictOnDelete();
            $table->unsignedSmallInteger('round');
            $table->string('mode', 16); // in_person | video | phone
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('location')->nullable();
            $table->string('meeting_url', 500)->nullable();
            $table->text('instructions')->nullable();
            $table->string('status', 16)->default('scheduled'); // scheduled | completed | cancelled
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('completion_remarks')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['application_id', 'round'], 'application_interviews_round_unique');
            $table->index(['status', 'starts_at'], 'application_interviews_status_start_idx');
        });

        Schema::create('interview_panelists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_interview_id')->constrained('application_interviews')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['application_interview_id', 'employee_id'], 'interview_panelists_unique');
            $table->index('employee_id', 'interview_panelists_employee_idx');
        });

        Schema::create('interview_reschedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_interview_id')->constrained('application_interviews')->cascadeOnDelete();
            $table->dateTime('previous_starts_at');
            $table->dateTime('previous_ends_at');
            $table->dateTime('new_starts_at');
            $table->dateTime('new_ends_at');
            $table->text('reason')->nullable();
            $table->foreignId('rescheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rescheduled_at')->useCurrent();
            $table->timestamps();
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE application_interviews ADD CONSTRAINT application_interviews_status_check CHECK (status IN ('scheduled', 'completed', 'cancelled'))");
            DB::statement("ALTER TABLE application_interviews ADD CONSTRAINT application_interviews_mode_check CHECK (mode IN ('in_person', 'video', 'phone'))");
            DB::statement('ALTER TABLE application_interviews ADD CONSTRAINT application_interviews_time_check CHECK (ends_at > starts_at)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_reschedules');
        Schema::dropIfExists('interview_panelists');
        Schema::dropIfExists('application_interviews');
    }
};
