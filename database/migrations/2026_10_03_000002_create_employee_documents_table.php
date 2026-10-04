<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files kept on an employee's record. The file itself lives on the private
 * disk (never public storage) and is only served through authorized routes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('employee_document_type_id')->constrained('employee_document_types')->restrictOnDelete();
            $table->string('original_name');
            $table->string('disk', 32)->default('local');
            $table->string('file_path')->unique();
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('file_size')->comment('bytes');
            $table->text('description')->nullable();
            $table->date('expires_on')->nullable();
            // Where the file came from: an HR upload, or copied from another module (e.g. recruitment).
            $table->string('source', 32)->default('upload');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();

            $table->index(['employee_id', 'employee_document_type_id'], 'emp_docs_employee_type_idx');
            $table->index('expires_on', 'emp_docs_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
