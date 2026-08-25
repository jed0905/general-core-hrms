<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            // Add current_status_id column
            $table->foreignId('current_status_id')
                ->nullable()
                ->after('id') // you can adjust the position
                ->constrained('leave_statuses')
                ->nullOnDelete()
                ->comment('Points to the latest leave_status record for quick access');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropForeign(['current_status_id']);
            $table->dropColumn('current_status_id');
        });
    }
};
