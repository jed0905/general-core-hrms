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
        Schema::create('special_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_type_id')->constrained('leaves');
            $table->string('name');
            $table->string('shortcut');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('special_leaves');
    }
};
