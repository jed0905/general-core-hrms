<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The column was created as `commment`; the model has always used `comment`.
     */
    public function up(): void
    {
        if (Schema::hasColumn('leave_application_comments', 'commment')) {
            Schema::table('leave_application_comments', function (Blueprint $table) {
                $table->renameColumn('commment', 'comment');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_application_comments', 'comment')) {
            Schema::table('leave_application_comments', function (Blueprint $table) {
                $table->renameColumn('comment', 'commment');
            });
        }
    }
};
