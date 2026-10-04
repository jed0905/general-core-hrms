<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recruitment phase 8: date indexes for the recruitment reports' period
 * filters. These tables grow with every application and event, and none of
 * these columns was indexed on its own. Indexes only; no data changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', fn (Blueprint $table) => $table->index('applied_at', 'applications_applied_at_idx'));
        Schema::table('application_stage_histories', fn (Blueprint $table) => $table->index(['acted_at', 'action'], 'application_history_acted_idx'));
        Schema::table('job_offers', fn (Blueprint $table) => $table->index('responded_at', 'job_offers_responded_at_idx'));
        Schema::table('recruitment_portal_events', fn (Blueprint $table) => $table->index(['occurred_at', 'event'], 'portal_events_occurred_idx'));
    }

    public function down(): void
    {
        Schema::table('applications', fn (Blueprint $table) => $table->dropIndex('applications_applied_at_idx'));
        Schema::table('application_stage_histories', fn (Blueprint $table) => $table->dropIndex('application_history_acted_idx'));
        Schema::table('job_offers', fn (Blueprint $table) => $table->dropIndex('job_offers_responded_at_idx'));
        Schema::table('recruitment_portal_events', fn (Blueprint $table) => $table->dropIndex('portal_events_occurred_idx'));
    }
};
