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
        Schema::create('employee_leave_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees');
            $table->foreigniD('leave_type_id')->constrained('leave_types');
            $table->foreignId('leave_balance_id')->constrained('employee_leave_balances');

            $table->string('transaction_type');
            $table->decimal('amount');
            

            $table->string('total_earned');
            $table->string('credit_addition')->nullable();
            $table->string('credit_deduction')->nullable();
            $table->string('balance')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_leave_ledger');
    }
};
