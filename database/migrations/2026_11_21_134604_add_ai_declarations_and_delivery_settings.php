<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How much of the artwork, the recording, the composition and the
     * lyrics was made by AI ("none", "partly", "all"; null is not yet
     * declared), and what a label has agreed with its distributors.
     */
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->string('ai_artwork', 8)->nullable();
        });

        Schema::table('tracks', function (Blueprint $table) {
            $table->string('ai_recording', 8)->nullable();
            $table->string('ai_composition', 8)->nullable();
            $table->string('ai_lyrics', 8)->nullable();
        });

        Schema::table('labels', function (Blueprint $table) {
            $table->json('delivery_settings')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropColumn('ai_artwork');
        });

        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn(['ai_recording', 'ai_composition', 'ai_lyrics']);
        });

        Schema::table('labels', function (Blueprint $table) {
            $table->dropColumn('delivery_settings');
        });
    }
};
