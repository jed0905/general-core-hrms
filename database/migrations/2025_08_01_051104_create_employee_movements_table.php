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
        Schema::create('employee_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->foreignId('movement_type_id')
                ->constrained('employee_movement_types');

            $table->date('effective_date');

            $table->string('reference_number')->nullable();

            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();

            $table->enum('status', [
                'draft',
                'pending',
                'approved',
                'rejected',
                'implemented',
                'cancelled',
            ])->default('draft');

            $table->foreignId('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('approved_at')->nullable();
            $table->dateTime('implemented_at')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'effective_date']);
            $table->index(['movement_type_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_movements');
    }
};
