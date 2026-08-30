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
        Schema::create('user_otp_codes', function (Blueprint $table) {
            $table->id();

            // Link to employee table (foreign key)
            $table->string('employee_number');

            $table->foreign('employee_number')
                ->references('employee_number')
                ->on('employees')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            // Store OTP code (hashed for security)
            $table->string('otp');

            // Track expiry time
            $table->timestamp('expires_at');

            // Flag if OTP was already used
            $table->boolean('used')->default(false);

            // Indicate module where the request originated
            $table->enum('action', ['register', 'login', 'change_password', 'change_email', 'enable_two_factor']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_otp_codes');
    }
};
