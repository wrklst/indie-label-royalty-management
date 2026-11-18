<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A UPC / EAN identifies one release. Releases without one are not
     * affected: NULL never collides.
     */
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->unique(['label_id', 'upc']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropUnique(['label_id', 'upc']);
        });
    }
};
