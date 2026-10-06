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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('project_id', 64);
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->foreignId('source_file_id')->constrained()->cascadeOnDelete();
            $table->string('task_id', 32);
            $table->unsignedBigInteger('number');
            $table->string('section', 20);
            $table->unsignedInteger('position');
            $table->boolean('checked');
            $table->text('title');
            $table->json('metadata');
            $table->date('due')->nullable();
            $table->date('started')->nullable();
            $table->date('since')->nullable();
            $table->date('done')->nullable();
            $table->date('cancelled')->nullable();
            $table->text('waiting')->nullable();
            $table->text('evidence')->nullable();
            $table->string('from_section', 20)->nullable();
            $table->text('proof')->nullable();
            $table->json('notes');
            $table->text('notes_text');
            $table->unsignedInteger('line_start');
            $table->unsignedInteger('line_end');

            $table->unique(['project_id', 'number']);
            // Search: MySQL's FULLTEXT index; the SQLite test database falls back to LIKE.
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->fullText(['title', 'proof', 'notes_text']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
