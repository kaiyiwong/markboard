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
        Schema::create('projects', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->text('name');
            $table->text('path');
            $table->string('category', 20);
            $table->string('status', 10);
            $table->text('next_milestone');
            $table->text('docs');
            $table->unsignedInteger('registry_order');
            $table->unsignedInteger('category_rank');
            $table->boolean('folder_found');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
