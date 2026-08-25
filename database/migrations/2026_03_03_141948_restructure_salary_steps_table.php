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
        Schema::table('salary_steps', function (Blueprint $table) {

            // 1️⃣ Drop salary amount column if it exists
            if (Schema::hasColumn('salary_steps', 'amount')) {
                $table->dropColumn('amount');
            }

            // 2️⃣ Ensure step_number exists
            if (!Schema::hasColumn('salary_steps', 'salary_step_no')) {
                $table->unsignedTinyInteger('salary_step_no')
                      ->after('salary_grade_id');
            }

            // 3️⃣ Add unique constraint (SG + Step must be unique)
            $table->unique(
                ['salary_grade_id', 'salary_step_no'],
                'salary_grade_step_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('salary_steps', function (Blueprint $table) {

            $table->dropUnique('salary_grade_step_unique');

            $table->decimal('amount', 12, 2)->nullable();
        });
    }
};
