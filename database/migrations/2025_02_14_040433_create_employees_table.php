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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->string('photo')->nullable();

            // Personal information
            $table->string('employee_number')->unique();
            $table->string('emp_last_name');
            $table->string('emp_first_name');
            $table->string('emp_middle_name')->nullable();
            $table->string('emp_suffix')->nullable();
            $table->date('emp_birthday')->nullable();

            $table->foreignId('emp_nationality_id')
                ->nullable()
                ->constrained('nationalities')
                ->nullOnDelete();

            $table->string('emp_sex');
            $table->string('emp_marital_status')->nullable();

            // Contact details
            $table->string('street1')->nullable();
            $table->string('street2')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('country_id')->nullable();
            $table->string('home_telephone_no')->nullable();
            $table->string('mobile_no')->nullable();
            $table->string('work_no')->nullable();
            $table->string('work_email')->nullable();
            $table->string('other_email')->nullable();

            // E-Signature
            $table->string('e_signature_path')->nullable();

            // Job details
            $table->date('joined_date')->nullable();

            $table->foreignId('job_title_id')
                ->nullable()
                ->constrained('job_titles')
                ->nullOnDelete();

            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            $table->foreignId('location_id')
                ->nullable()
                ->constrained('locations')
                ->nullOnDelete();

            $table->foreignId('employment_status_id')
                ->nullable()
                ->constrained('employment_statuses')
                ->nullOnDelete();

            $table->string('status')->default('active');

            $table->foreignId('supervisor_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};