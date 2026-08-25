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
        Schema::create('leave_statuses', function (Blueprint $table) {
            $table->id();

            // Link to leave application
            $table->foreignId('leave_application_id')
                ->constrained('leave_applications')
                ->cascadeOnDelete();

            // Status enum
            $table->enum('status', [
                'pending',
                'for approval',
                'for disapproval',
                'certified',
                'approved',
                'disapproved',
                'cancelled'
            ]);

            // Optional remarks
            $table->text('remarks')->nullable();

            // Who acted on this status
            $table->foreignId('acted_by')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            // When the action was performed
            $table->timestamp('acted_at')->nullable();

            $table->timestamps();

            // Index for faster lookups
            $table->index(['leave_application_id', 'acted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_statuses');
    }
};
