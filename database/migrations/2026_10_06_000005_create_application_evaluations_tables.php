<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Interview scorecards: one per application + interview + evaluator (database
 * unique), with one normalized score row per criterion. The criterion name is
 * copied onto the score so renaming a criterion never changes a past scorecard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->restrictOnDelete();
            $table->foreignId('application_interview_id')->constrained('application_interviews')->restrictOnDelete();
            $table->foreignId('evaluator_employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('evaluator_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('draft'); // draft | submitted
            $table->string('recommendation', 32)->nullable(); // recommend | neutral | do_not_recommend
            $table->text('comments')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'application_interview_id', 'evaluator_employee_id'], 'application_evaluations_unique');
            $table->index('evaluator_employee_id', 'application_evaluations_evaluator_idx');
        });

        Schema::create('application_evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_evaluation_id')->constrained('application_evaluations')->cascadeOnDelete();
            $table->foreignId('evaluation_criterion_id')->constrained('evaluation_criteria')->restrictOnDelete();
            $table->string('criterion_name');
            $table->unsignedTinyInteger('rating');
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->unique(['application_evaluation_id', 'evaluation_criterion_id'], 'evaluation_scores_unique');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE application_evaluations ADD CONSTRAINT application_evaluations_status_check CHECK (status IN ('draft', 'submitted'))");
            DB::statement("ALTER TABLE application_evaluations ADD CONSTRAINT application_evaluations_recommendation_check CHECK (recommendation IS NULL OR recommendation IN ('recommend', 'neutral', 'do_not_recommend'))");
            DB::statement("ALTER TABLE application_evaluations ADD CONSTRAINT application_evaluations_submitted_check CHECK (status = 'draft' OR (submitted_at IS NOT NULL AND recommendation IS NOT NULL))");
            DB::statement('ALTER TABLE application_evaluation_scores ADD CONSTRAINT evaluation_scores_rating_check CHECK (rating BETWEEN 1 AND 5)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_evaluation_scores');
        Schema::dropIfExists('application_evaluations');
    }
};
