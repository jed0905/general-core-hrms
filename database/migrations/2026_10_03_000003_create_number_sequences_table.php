<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable, concurrency-safe document/record numbering (employee numbers
 * now; requisition and application numbers later). One row per sequence key;
 * NumberSequenceService locks the row while it issues a number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            // Prefix/suffix may contain {YYYY}, {YY} and {MM} date tokens.
            $table->string('prefix', 32)->default('');
            $table->string('suffix', 32)->default('');
            $table->unsignedTinyInteger('padding')->default(5);
            $table->unsignedBigInteger('next_number')->default(1);
            // When false, a value must be entered manually (nothing is generated).
            $table->boolean('auto_generate')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('number_sequences')->insert([
            'key' => 'employee_number',
            'name' => 'Employee number',
            'prefix' => 'EMP-',
            'suffix' => '',
            'padding' => 5,
            'next_number' => 1,
            'auto_generate' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
