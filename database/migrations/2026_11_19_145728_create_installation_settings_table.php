<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Settings of the whole installation, such as the file storage, and
     * who may change them. The first account of an installation can.
     */
    public function up(): void
    {
        Schema::create('installation_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            // Encrypted JSON.
            $table->text('value');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('installation_admin')->default(false);
        });

        DB::table('users')->where('id', DB::table('users')->min('id'))->update(['installation_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('installation_admin');
        });

        Schema::dropIfExists('installation_settings');
    }
};
