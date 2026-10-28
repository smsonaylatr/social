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
        Schema::table('live_streams', function (Blueprint $table) {
            $table->string('audio_mode')->default('original')->after('mute_original_audio');
            $table->string('bg_music_source')->default('preset')->after('audio_mode');
            $table->string('bg_music_preset')->default('lofi_chill')->after('bg_music_source');
            $table->string('bg_music_path')->nullable()->after('bg_music_preset');
            $table->unsignedTinyInteger('bg_music_volume')->default(100)->after('bg_music_path');
            $table->unsignedTinyInteger('original_audio_volume')->default(100)->after('bg_music_volume');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn([
                'audio_mode',
                'bg_music_source',
                'bg_music_preset',
                'bg_music_path',
                'bg_music_volume',
                'original_audio_volume',
            ]);
        });
    }
};
