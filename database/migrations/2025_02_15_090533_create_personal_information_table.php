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
        Schema::create('personal_information', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->unique() // 👈 ensures employee_id is not repeated
                ->constrained('employees')
                ->onDelete('cascade');
            $table->string('firstname');
            $table->string('lastname');
            $table->string('middlename')->nullable();
            $table->string('suffix')->nullable();
            $table->string('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('sex')->nullable();
            $table->string('civil_status')->nullable();
            $table->string('height')->nullable();
            $table->string('weight')->nullable();
            $table->string('blood_type')->nullable();
            $table->string('gsis_id_no')->nullable();
            $table->string('pag_ibig_id_no')->nullable();
            $table->string('philhealth_id_no')->nullable();
            $table->string('sss_id_no')->nullable();
            $table->string('tin_id_no')->nullable();
            $table->enum('citizenship', ['filipino', 'dual'])->default('filipino');
            $table->enum('dual_citizenship_type', ['birth', 'naturalization'])->nullable();
            $table->string('dual_citizenship_country')->nullable();
            $table->string('country')->nullable();
            $table->string('residential_house_no')->nullable();
            $table->string('residential_street')->nullable();
            $table->string('residential_subdivision')->nullable();
            $table->string('residential_barangay')->nullable();
            $table->string('residential_city_municipality')->nullable();
            $table->string('residential_province')->nullable();
            $table->string('residential_zip_code')->nullable();
            $table->string('permanent_house_no')->nullable();
            $table->string('permanent_street')->nullable();
            $table->string('permanent_subdivision')->nullable();
            $table->string('permanent_barangay')->nullable();
            $table->string('permanent_city_municipality')->nullable();
            $table->string('permanent_province')->nullable();
            $table->string('permanent_zip_code')->nullable();
            $table->string('telephone_no')->nullable();
            $table->string('mobile_no')->nullable();
            $table->string('email');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_information');
    }
};
