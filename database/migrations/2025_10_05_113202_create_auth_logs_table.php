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
         Schema::create('auth_logs', function (Blueprint $table) {
            $table->id();
            $table->string('auth_role')->nullable();
            $table->string('auth_type');
            $table->string('auth_id');
            $table->string('attempt_status');
            $table->string('user_agent');
            $table->ipAddress('local_ip_address')->nullable();
            $table->ipAddress('public_ip_address')->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth_logs');
    }
};
