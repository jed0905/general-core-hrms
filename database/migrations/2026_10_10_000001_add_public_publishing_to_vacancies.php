<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Careers portal publishing. vacancies.visibility (internal | external | both)
 * already says who a vacancy is for; publishing is the explicit HR action that
 * puts an open, external vacancy on the public careers site. public_slug is
 * set on first publication and never changes, so public links stay stable and
 * internal ids are never exposed. Salary is shown publicly only on opt-in.
 *
 * applicant_document_types.required_online: document types a candidate must
 * submit when applying through the careers site.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacancies', function (Blueprint $table) {
            $table->string('public_slug', 120)->nullable()->unique('vacancies_public_slug_unique');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('show_salary_publicly')->default(false);

            $table->index(['status', 'published_at'], 'vacancies_public_idx');
        });

        Schema::table('applicant_document_types', function (Blueprint $table) {
            $table->boolean('required_online')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('applicant_document_types', function (Blueprint $table) {
            $table->dropColumn('required_online');
        });

        Schema::table('vacancies', function (Blueprint $table) {
            $table->dropIndex('vacancies_public_idx');
            $table->dropForeign(['published_by']);
            $table->dropUnique('vacancies_public_slug_unique');
            $table->dropColumn(['public_slug', 'published_at', 'published_by', 'show_salary_publicly']);
        });
    }
};
