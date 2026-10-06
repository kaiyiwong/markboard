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
        Schema::create('pipeline_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_file_id')->constrained()->cascadeOnDelete();
            $table->string('project_id', 64);
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->text('company');
            $table->text('role');
            $table->string('stage', 20)->nullable();
            $table->text('next_action');
            $table->date('date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pipeline_rows');
    }
};
