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
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();

            $table->string('name');

            $table->string('code')
                ->nullable()
                ->unique();

            $table->string('type')
                ->default('regular');

            $table->boolean('is_paid')
                ->default(true);

            $table->boolean('is_working_day')
                ->default(false);

            $table->boolean('is_recurring')
                ->default(false);

            $table->text('description')
                ->nullable();

            $table->string('status')
                ->default('active');

            $table->timestamps();

            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
