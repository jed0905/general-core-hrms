<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_application_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')
                ->constrained('leave_applications')
                ->cascadeOnDelete();

            $table->foreignId('approver_id')
                ->constrained('employees');

            $table->unsignedTinyInteger('approval_order');

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'skipped',
            ]);

            $table->dateTime('acted_at')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(
                ['leave_application_id', 'approval_order'],
                'leave_app_approval_order_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_application_approvals');
    }
};
