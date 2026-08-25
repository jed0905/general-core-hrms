<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_movements', function (Blueprint $table) {

            if (!Schema::hasColumn('employee_movements', 'old_employee_number')) {
                $table->string('old_employee_number')->nullable()->after('employee_id');
            }

            if (!Schema::hasColumn('employee_movements', 'new_employee_number')) {
                $table->string('new_employee_number')->nullable()->after('old_employee_number');
            }
        });

        if (Schema::hasColumn('employee_movements', 'employee_number')) {
            Schema::table('employee_movements', function (Blueprint $table) {
                $table->dropColumn('employee_number');
            });
        }
    }

    public function down(): void
    {
        Schema::table('employee_movements', function (Blueprint $table) {

            if (Schema::hasColumn('employee_movements', 'old_employee_number')) {
                $table->dropColumn('old_employee_number');
            }

            if (Schema::hasColumn('employee_movements', 'new_employee_number')) {
                $table->dropColumn('new_employee_number');
            }

            if (!Schema::hasColumn('employee_movements', 'employee_number')) {
                $table->string('employee_number')->nullable()->after('employee_id');
            }
        });
    }
};
