<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Selection decisions (append-only history). The latest row per application is
 * its current decision; a revision is a new row, never an edit. Each row keeps
 * the evaluation summary the decision was made on. "selected" holds one of the
 * vacancy's openings (vacancies.filled_count) until a later decision releases it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->restrictOnDelete();
            $table->foreignId('vacancy_id')->constrained('vacancies')->restrictOnDelete();
            $table->foreignId('applicant_id')->constrained('applicants')->restrictOnDelete();
            $table->string('decision', 16); // selected | not_selected
            // True when the system released an opening (application rejected/withdrawn), not a person's decision.
            $table->boolean('is_automatic')->default(false);
            $table->text('remarks')->nullable();
            // Evaluation context at decision time.
            $table->unsignedSmallInteger('completed_interviews')->default(0);
            $table->unsignedSmallInteger('submitted_evaluations')->default(0);
            $table->unsignedSmallInteger('recommend_count')->default(0);
            $table->unsignedSmallInteger('neutral_count')->default(0);
            $table->unsignedSmallInteger('do_not_recommend_count')->default(0);
            $table->decimal('average_rating', 4, 2)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->useCurrent();
            $table->timestamps();

            $table->index(['application_id', 'id'], 'application_selections_app_idx');
            $table->index(['vacancy_id', 'decision'], 'application_selections_vacancy_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE application_selections ADD CONSTRAINT application_selections_decision_check CHECK (decision IN ('selected', 'not_selected'))");
            DB::statement('ALTER TABLE application_selections ADD CONSTRAINT application_selections_rating_check CHECK (average_rating IS NULL OR (average_rating >= 1 AND average_rating <= 5))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('application_selections');
    }
};
