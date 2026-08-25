<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // Who made the change
            $table->string('action'); // create, update, delete
            $table->string('model_type'); // Eloquent model class
            $table->unsignedBigInteger('model_id'); // ID of the affected record
            $table->json('old_values')->nullable(); // Before change
            $table->json('new_values')->nullable(); // After change
            $table->timestamps(); // When the change occurred

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_trails');
    }
};
