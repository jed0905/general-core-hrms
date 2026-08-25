<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreignId('leave_id')->constrained(table: 'leaves');
            $table->string('location_type')->nullable();
            $table->string('location_within_philippines')->nullable();
            $table->string('location_abroad')->nullable();
            $table->string('in_hospital')->nullable();
            $table->string('out_hospital')->nullable();
            $table->string('illness')->nullable();
            $table->string('sick_leave_type')->nullable();
            $table->string('study_leave_application')->nullable();
            $table->string('other_purposes')->nullable();
            $table->string('no_of_days')->nullable();
            $table->string('from')->nullable();
            $table->string('to')->nullable();
            $table->string('leave_duration')->nullable();
            $table->string('commutation')->nullable();
            $table->string('credits')->nullable();
            $table->string('status')->default('pending');
            $table->text('cancellation_reason')->nullable();
            $table->string('remarks')->nullable();
            $table->string('revoked')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_applications');
    }
};
