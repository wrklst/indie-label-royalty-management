<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->string('status', 20)->default('done');
            $table->string('release_type', 20)->nullable();
            $table->string('version')->nullable();
            $table->string('genre')->nullable();
            $table->string('subgenre')->nullable();
            $table->string('language', 8)->nullable();
            $table->string('explicit', 10)->nullable();
            $table->string('p_line')->nullable();
            $table->string('c_line')->nullable();
            $table->date('original_released_on')->nullable();
            $table->date('preorder_on')->nullable();
            $table->longText('press_release')->nullable();
            $table->text('key_facts')->nullable();
            $table->text('todo')->nullable();
            $table->string('files_url', 2000)->nullable();
            $table->json('blocked_services')->nullable();
            $table->json('blocked_territories')->nullable();
        });

        Schema::table('tracks', function (Blueprint $table) {
            $table->unsignedSmallInteger('disc_number')->nullable();
            $table->string('version')->nullable();
            $table->string('genre')->nullable();
            $table->string('language', 8)->nullable();
            $table->string('explicit', 10)->nullable();
            $table->decimal('bpm', 6, 2)->nullable();
            $table->string('musical_key', 20)->nullable();
            $table->unsignedInteger('preview_start')->nullable();
            $table->string('p_line')->nullable();
            $table->json('credits')->nullable();
            $table->longText('lyrics')->nullable();
        });

        Schema::create('artist_release', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('primary');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['release_id', 'artist_id', 'role']);
        });

        Schema::create('artist_track', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('primary');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['track_id', 'artist_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artist_track');
        Schema::dropIfExists('artist_release');

        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn(['disc_number', 'version', 'genre', 'language', 'explicit', 'bpm', 'musical_key', 'preview_start', 'p_line', 'credits', 'lyrics']);
        });

        Schema::table('releases', function (Blueprint $table) {
            $table->dropColumn([
                'status', 'release_type', 'version', 'genre', 'subgenre', 'language', 'explicit', 'p_line', 'c_line', 'original_released_on',
                'preorder_on', 'press_release', 'key_facts', 'todo', 'files_url', 'blocked_services', 'blocked_territories',
            ]);
        });
    }
};
