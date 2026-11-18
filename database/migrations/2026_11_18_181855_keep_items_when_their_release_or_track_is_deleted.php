<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Items carry transactions, so they must outlive the release or track
     * they were assigned to and show up as unassigned instead.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['release_id']);
            $table->dropForeign(['track_id']);
            $table->foreign('release_id')->references('id')->on('releases')->nullOnDelete();
            $table->foreign('track_id')->references('id')->on('tracks')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['release_id']);
            $table->dropForeign(['track_id']);
            $table->foreign('release_id')->references('id')->on('releases')->cascadeOnDelete();
            $table->foreign('track_id')->references('id')->on('tracks')->cascadeOnDelete();
        });
    }
};
