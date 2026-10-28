<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('live_streams')) {
            return;
        }

        Schema::create('live_streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            // Video Kaynağı & Döngü Ayarları
            $table->string('source_type')->default('pool'); // pool, playlist, custom
            $table->foreignId('playlist_id')->nullable()->constrained('playlists')->nullOnDelete();
            $table->json('custom_video_ids')->nullable();
            $table->string('play_order')->default('sequential'); // sequential, shuffle
            $table->string('aspect_ratio_filter')->default('all'); // all, 9:16, 16:9

            // Kodlama (FFmpeg) Ayarları
            $table->string('resolution_mode')->default('vertical_1080p'); // vertical_1080p, vertical_720p, horizontal_1080p, horizontal_720p, copy
            $table->unsignedInteger('bitrate_kbps')->default(2500);
            $table->unsignedInteger('fps')->default(30);
            $table->boolean('is_loop')->default(true);
            $table->boolean('auto_restart')->default(true);
            $table->boolean('mute_original_audio')->default(false);

            // YouTube Live Hedefi
            $table->boolean('youtube_enabled')->default(true);
            $table->foreignId('youtube_account_id')->nullable()->constrained('social_accounts')->nullOnDelete();
            $table->string('youtube_rtmp_url')->default('rtmp://a.rtmp.youtube.com/live2');
            $table->text('youtube_stream_key')->nullable();
            $table->string('youtube_broadcast_id')->nullable();
            $table->string('youtube_watch_url')->nullable();

            // Instagram Live Hedefi (Instagram Live Producer RTMPS)
            $table->boolean('instagram_enabled')->default(false);
            $table->foreignId('instagram_account_id')->nullable()->constrained('social_accounts')->nullOnDelete();
            $table->string('instagram_rtmp_url')->default('rtmps://edgetee-upload-iad.xx.fbcdn.net:443/rtmp/');
            $table->text('instagram_stream_key')->nullable();

            // Ek Özel RTMP Hedefi (TikTok Live / Facebook Live / Restream vb.)
            $table->boolean('custom_rtmp_enabled')->default(false);
            $table->string('custom_rtmp_name')->nullable();
            $table->string('custom_rtmp_url')->nullable();
            $table->text('custom_rtmp_key')->nullable();

            // Süreç (Process) ve Durum Takibi
            $table->string('status')->default('stopped'); // stopped, starting, running, restarting, error
            $table->unsignedBigInteger('process_pid')->nullable();
            $table->unsignedInteger('total_videos_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->unsignedInteger('restart_count')->default(0);
            $table->text('last_error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_streams');
    }
};
