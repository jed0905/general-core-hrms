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
        Schema::create('leave_application_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')
                ->constrained('leave_applications')
                ->cascadeOnDelete();

            $table->date('leave_date');

            $table->enum('duration_type', [
                'full_day',
                'half_day',
                'hours',
            ]);

            $table->decimal('hours', 5, 2)->nullable(); // actual hours taken
            $table->decimal('day_fraction', 5, 4)->nullable(); // actual leave credit consumed

            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->boolean('is_paid')->default(true);

            $table->timestamps();

            $table->unique([
                'leave_application_id',
                'leave_date',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_application_dates');
    }
};
