<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Careers portal accounts and audit. A portal account is a login for ONE
 * existing-style applicant record (applicants table): no second applicant or
 * application store exists. Accounts are a separate authentication domain
 * from employee/HR users (own guard, own reset tokens, no roles).
 *
 * Registration stores the submitted profile (encrypted) until the candidate
 * proves the email address by following the emailed link and choosing a
 * password; only then is the account linked to (or creates) the applicant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicant_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->nullable()->unique('applicant_accounts_applicant_unique')->constrained('applicants')->restrictOnDelete();
            $table->string('email')->unique('applicant_accounts_email_unique'); // normalized (lowercase, trimmed)
            $table->string('password')->nullable(); // null until the emailed link is used
            $table->text('pending_profile')->nullable(); // encrypted registration data, cleared on activation
            $table->timestamp('email_verified_at')->nullable();
            $table->string('status', 16)->default('active'); // active | disabled
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('applicant_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('careers_settings', function (Blueprint $table) {
            $table->id();
            $table->string('headline');
            $table->text('introduction')->nullable();
            $table->text('application_instructions')->nullable();
            $table->text('privacy_notice');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('recruitment_portal_events', function (Blueprint $table) {
            $table->id();
            $table->string('event', 40);
            $table->foreignId('applicant_account_id')->nullable()->constrained('applicant_accounts')->nullOnDelete();
            $table->foreignId('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->foreignId('vacancy_id')->nullable()->constrained('vacancies')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // HR actor (publishing)
            $table->text('remarks')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['applicant_id', 'id'], 'portal_events_applicant_idx');
            $table->index(['vacancy_id', 'id'], 'portal_events_vacancy_idx');
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE applicant_accounts ADD CONSTRAINT applicant_accounts_status_check CHECK (status IN ('active', 'disabled'))");
            DB::statement('ALTER TABLE applicant_accounts ADD CONSTRAINT applicant_accounts_activation_check CHECK (applicant_id IS NULL OR (password IS NOT NULL AND email_verified_at IS NOT NULL))');
        }

        $now = now();
        DB::table('careers_settings')->insert([
            'headline' => 'Join our team',
            'introduction' => 'Browse our open positions and apply online.',
            'application_instructions' => 'Create an account, complete your profile, then apply to the positions that interest you. You can follow your applications under "My Applications".',
            'privacy_notice' => 'We collect and process the personal information you provide only to evaluate your application for employment and to contact you about it. Your information is stored securely and is accessible only to our recruitment team. You may ask us to update or remove your information by contacting us.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_portal_events');
        Schema::dropIfExists('careers_settings');
        Schema::dropIfExists('applicant_password_reset_tokens');
        Schema::dropIfExists('applicant_accounts');
    }
};
