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
        Schema::create('university_activities', function (Blueprint $table) {
            $table->id();

            // Title of the activity or advisory
            $table->string('title');

            // Type: event, advisory, suspension, work-from-home, etc.
            $table->enum('type', [
                'event',
                'suspension',
                'work_from_home',
                'advisory'
            ])->default('event');

            // Optional description/details
            $table->text('description')->nullable();

            // Date range of the activity/advisory
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();

            // Whether this affects classes/work
            // $table->boolean('affects_all')->default(false);
            // $table->boolean('affects_classes')->default(false);
            // $table->boolean('affects_work')->default(false);

            // Status: planned, ongoing, completed, cancelled
            $table->enum('status', [
                'planned',
                'ongoing',
                'completed',
                'cancelled'
            ])->default('planned');

            $table->string('document_control_number')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('university_activities');
    }
};
