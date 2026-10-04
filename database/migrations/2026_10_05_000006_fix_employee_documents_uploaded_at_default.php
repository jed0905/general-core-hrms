<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * employee_documents.uploaded_at was created as a bare NOT NULL timestamp. On
 * MySQL/MariaDB servers with explicit_defaults_for_timestamp = OFF that becomes
 * "DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP", so editing a
 * document's details would overwrite when it was uploaded. Redefine it with an
 * explicit default and no ON UPDATE. Values are preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->timestamp('uploaded_at')->useCurrent()->change();
        });
    }

    public function down(): void
    {
        // Intentionally not restoring the auto-updating definition.
    }
};
