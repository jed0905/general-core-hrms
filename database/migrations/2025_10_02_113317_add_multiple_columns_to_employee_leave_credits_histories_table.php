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
        Schema::table('employee_leave_credits_histories', function (Blueprint $table) {
            $table->string('special_type')->nullable();
            $table->string('document_type_number')->nullable();
            $table->date('expiration_date_from')->nullable();
            $table->date('expiration_date_to')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_leave_credits_histories', function (Blueprint $table) {
            //
        });
    }
};
