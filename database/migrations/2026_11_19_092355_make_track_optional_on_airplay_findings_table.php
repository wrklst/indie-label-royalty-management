<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A finding for a play of a track that is not in the catalog has no track.
     */
    public function up(): void
    {
        Schema::table('airplay_findings', function (Blueprint $table) {
            $table->foreignId('track_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('airplay_findings', function (Blueprint $table) {
            $table->foreignId('track_id')->nullable(false)->change();
        });
    }
};
