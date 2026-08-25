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
        Schema::create('operating_units', function (Blueprint $table) {
            $table->id();
            $table->string('prefix_id')->unique();
            $table->string('name')->unique();
            $table->string('shortcut')->unique();
            $table->string('barangay');
            $table->string('city_municipality');
            $table->string('province');
            $table->string('zip');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operating_units');
    }
};
