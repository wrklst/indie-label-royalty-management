<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A play a society paid for without naming the broadcaster has no station.
     */
    public function up(): void
    {
        Schema::table('airplays', function (Blueprint $table) {
            $table->string('station')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('airplays', function (Blueprint $table) {
            $table->string('station')->nullable(false)->change();
        });
    }
};
