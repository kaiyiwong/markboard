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
        Schema::create('file_versions', function (Blueprint $table) {
            $table->id();
            $table->text('path');
            $table->char('path_hash', 64);
            $table->char('hash', 64);
            $table->longText('content')->charset('binary'); // the bytes as read, even invalid UTF-8
            $table->timestamp('last_seen_at');

            $table->unique(['path_hash', 'hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_versions');
    }
};
