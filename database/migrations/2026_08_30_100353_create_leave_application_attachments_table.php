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
        Schema::create('leave_application_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')
                ->constrained('leave_applications')
                ->cascadeOnDelete();

            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type');
            $table->decimal('file_size');

            $table->foreignId('uploaded_by')
                ->constrained('employees');
            $table->dateTime('uploaded_at');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_application_attachments');
    }
};
