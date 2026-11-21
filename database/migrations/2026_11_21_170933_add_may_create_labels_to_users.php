<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether an account may open a label of its own was read off it
     * having no label, which is as true of someone who was removed from
     * their last one as of someone new. It is now said outright.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('may_create_labels')->default(false)->after('installation_admin');
        });

        // Whoever runs a label or the installation today keeps the right.
        DB::table('users')->where('installation_admin', true)
            ->orWhereIn('id', DB::table('label_user')->where('role', 'admin')->select('user_id'))
            ->update(['may_create_labels' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('may_create_labels');
        });
    }
};
