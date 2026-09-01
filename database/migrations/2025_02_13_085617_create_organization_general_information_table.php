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
        Schema::create('organization_general_information', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('shortcut')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('country');
            $table->string('province');
            $table->string('city');
            $table->string('zip_code');
            $table->string('street1')->nullable();
            $table->string('street2')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_general_information');
    }
};
