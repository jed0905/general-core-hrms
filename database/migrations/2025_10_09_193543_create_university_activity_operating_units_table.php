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
        Schema::create('university_activity_operating_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')
                ->constrained('university_activities')
                ->onDelete('cascade');
            $table->foreignId('operating_unit_id')->constrained('operating_units');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('university_activity_operating_units');
    }
};
