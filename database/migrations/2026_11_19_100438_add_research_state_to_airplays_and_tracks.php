<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a queued research stands, so the page can show it.
     */
    public function up(): void
    {
        foreach (['airplays', 'tracks'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('research_state', 16)->nullable();
                $table->string('research_step', 500)->nullable();
                $table->timestamp('research_state_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['airplays', 'tracks'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['research_state', 'research_step', 'research_state_at']);
            });
        }
    }
};
