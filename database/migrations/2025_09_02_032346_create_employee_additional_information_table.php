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
        Schema::create('employee_additional_information', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');

            // Consanguinity/Affinity questions
            $table->boolean('consanguinity_third_degree_yes')->default(false);
            $table->boolean('consanguinity_third_degree_no')->default(false);
            $table->boolean('consanguinity_fourth_degree_yes')->default(false);
            $table->boolean('consanguinity_fourth_degree_no')->default(false);
            $table->text('consanguinity_details')->nullable();

            // Administrative offense
            $table->boolean('administrative_offense_yes')->default(false);
            $table->boolean('administrative_offense_no')->default(false);
            $table->text('administrative_offense_details')->nullable();

            // Criminal charge
            $table->boolean('criminal_charge_yes')->default(false);
            $table->boolean('criminal_charge_no')->default(false);
            $table->date('criminal_charge_date_filed')->nullable();
            $table->string('criminal_charge_status', 200)->nullable();

            // Conviction
            $table->boolean('conviction_yes')->default(false);
            $table->boolean('conviction_no')->default(false);
            $table->text('conviction_details')->nullable();

            // Separation from service
            $table->boolean('separation_from_service_yes')->default(false);
            $table->boolean('separation_from_service_no')->default(false);
            $table->text('separation_from_service_details')->nullable();

            // Election candidate
            $table->boolean('election_candidate_yes')->default(false);
            $table->boolean('election_candidate_no')->default(false);
            $table->text('election_candidate_details')->nullable();

            // Resigned for election
            $table->boolean('resigned_for_election_yes')->default(false);
            $table->boolean('resigned_for_election_no')->default(false);
            $table->text('resigned_for_election_details')->nullable();

            // Immigrant status
            $table->boolean('immigrant_status_yes')->default(false);
            $table->boolean('immigrant_status_no')->default(false);
            $table->string('immigrant_status_country', 100)->nullable();

            // Indigenous group
            $table->boolean('indigenous_group_yes')->default(false);
            $table->boolean('indigenous_group_no')->default(false);
            $table->string('indigenous_group_specify', 200)->nullable();

            // Person with disability
            $table->boolean('person_with_disability_yes')->default(false);
            $table->boolean('person_with_disability_no')->default(false);
            $table->string('person_with_disability_id_no', 50)->nullable();

            // Solo parent
            $table->boolean('solo_parent_yes')->default(false);
            $table->boolean('solo_parent_no')->default(false);
            $table->string('solo_parent_specify', 200)->nullable();



            $table->timestamps();

            // Foreign key constraint
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');

            // Indexes for better performance
            $table->index('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_additional_information');
    }
};
