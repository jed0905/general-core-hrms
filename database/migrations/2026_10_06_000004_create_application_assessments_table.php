<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Assessments (many per application). result_type is copied from the
 * assessment type when the assessment is created, so later type edits never
 * change how a recorded result reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->restrictOnDelete();
            $table->foreignId('assessment_type_id')->constrained('assessment_types')->restrictOnDelete();
            $table->string('result_type', 16); // score | pass_fail
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status', 16)->default('scheduled'); // scheduled | in_progress | completed | cancelled
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('maximum_score', 8, 2)->nullable();
            $table->boolean('passed')->nullable();
            $table->foreignId('assessor_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['application_id', 'status'], 'application_assessments_app_status_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE application_assessments ADD CONSTRAINT application_assessments_status_check CHECK (status IN ('scheduled', 'in_progress', 'completed', 'cancelled'))");
            DB::statement("ALTER TABLE application_assessments ADD CONSTRAINT application_assessments_result_type_check CHECK (result_type IN ('score', 'pass_fail'))");
            DB::statement('ALTER TABLE application_assessments ADD CONSTRAINT application_assessments_score_check CHECK (score IS NULL OR (score >= 0 AND (maximum_score IS NULL OR score <= maximum_score)))');
            DB::statement('ALTER TABLE application_assessments ADD CONSTRAINT application_assessments_max_check CHECK (maximum_score IS NULL OR maximum_score > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_assessments');
    }
};
