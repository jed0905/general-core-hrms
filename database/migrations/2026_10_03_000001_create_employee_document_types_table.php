<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Document categories for employee files. A lookup table (not an enum) so each
 * organization can maintain its own list; the defaults below are generic.
 */
return new class extends Migration
{
    public const DEFAULTS = [
        ['code' => 'resume', 'name' => 'Resume / CV', 'description' => 'Resume or curriculum vitae.'],
        ['code' => 'identification', 'name' => 'Identification', 'description' => 'Government-issued or company ID.'],
        ['code' => 'employment_contract', 'name' => 'Employment Contract', 'description' => 'Signed employment contract or offer letter.'],
        ['code' => 'certificate', 'name' => 'Certificate', 'description' => 'Diplomas, licenses, training and other certificates.'],
        ['code' => 'clearance', 'name' => 'Clearance', 'description' => 'Pre-employment or exit clearances.'],
        ['code' => 'medical', 'name' => 'Medical', 'description' => 'Medical certificates and fit-to-work results.'],
        ['code' => 'other', 'name' => 'Other', 'description' => 'Any other employee document.'],
    ];

    public function up(): void
    {
        Schema::create('employee_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('employee_document_types')->insert(array_map(
            fn (array $type) => $type + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            self::DEFAULTS
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_document_types');
    }
};
