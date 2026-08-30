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
        Schema::create('leave_application_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')
                    ->constrained('leave_applications')
                    ->cascadeOnDelete();
            
            $table->foreignId('commenter_id')
                ->constrained('employees');
            
            $table->text('commment');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_application_comments');
    }
};
