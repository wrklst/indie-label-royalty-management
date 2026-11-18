<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Everything the mechanicals, PPD and reserves tools need on top of
     * the rates the catalog already stores. All columns are optional, so
     * nothing changes for a label that never switches a tool on.
     */
    public function up(): void
    {
        Schema::table('labels', function (Blueprint $table) {
            $table->json('royalty_tool_settings')->nullable()->after('importer_settings');
        });

        Schema::table('tracks', function (Blueprint $table) {
            // Null follows the release.
            $table->boolean('accounts_mechanicals')->nullable()->after('duration');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->decimal('ppd_rate', 12, 6)->nullable()->after('is_additionals');
            // Marks the items a tool books on, e.g. "mechanicals-income".
            $table->string('tool_role', 32)->nullable()->after('ppd_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['ppd_rate', 'tool_role']);
        });

        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn('accounts_mechanicals');
        });

        Schema::table('labels', function (Blueprint $table) {
            $table->dropColumn('royalty_tool_settings');
        });
    }
};
