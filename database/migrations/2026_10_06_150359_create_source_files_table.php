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
        Schema::create('source_files', function (Blueprint $table) {
            $table->id();
            $table->string('project_id', 64)->nullable();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->text('path');
            $table->char('path_hash', 64)->unique();
            $table->char('hash', 64)->nullable();
            $table->unsignedBigInteger('mtime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->boolean('editable')->default(false);
            $table->json('errors')->nullable();
            $table->text('sync_error')->nullable();
            $table->timestamp('synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_files');
    }
};
