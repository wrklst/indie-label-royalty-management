<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The MP3 an audio master is played from, and how making it went.
     */
    public function up(): void
    {
        Schema::table('release_files', function (Blueprint $table) {
            $table->string('preview_path')->nullable();
            $table->string('preview_status', 20)->nullable();
            $table->text('preview_error')->nullable();
            $table->unsignedInteger('duration')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('release_files', function (Blueprint $table) {
            $table->dropColumn(['preview_path', 'preview_status', 'preview_error', 'duration']);
        });
    }
};
