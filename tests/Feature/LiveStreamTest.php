<?php

namespace Tests\Feature;

use App\Enums\VideoStatus;
use App\Models\LiveStream;
use App\Models\SocialAccount;
use App\Models\SocialAccountCredential;
use App\Models\SocialPlatform;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\LiveStream\LiveStreamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LiveStreamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_builds_concat_file_from_video_pool_in_sequential_order(): void
    {
        $workspace = Workspace::create(['name' => 'Test WS', 'slug' => 'test-ws']);

        Storage::disk('public')->put('videos/v1.mp4', 'fake-video-1');
        Storage::disk('public')->put('videos/v2.mp4', 'fake-video-2');

        $v1 = Video::create([
            'workspace_id' => $workspace->id,
            'title' => '1. Video',
            'original_filename' => 'v1.mp4',
            'storage_path' => 'videos/v1.mp4',
            'disk' => 'public',
            'file_hash' => md5('1'),
            'file_size' => 1024,
            'duration_seconds' => 15,
            'width' => 1080,
            'height' => 1920,
            'aspect_ratio' => '9:16',
            'status' => VideoStatus::READY,
        ]);

        $v2 = Video::create([
            'workspace_id' => $workspace->id,
            'title' => '2. Video',
            'original_filename' => 'v2.mp4',
            'storage_path' => 'videos/v2.mp4',
            'disk' => 'public',
            'file_hash' => md5('2'),
            'file_size' => 2048,
            'duration_seconds' => 25,
            'width' => 1080,
            'height' => 1920,
            'aspect_ratio' => '9:16',
            'status' => VideoStatus::READY,
        ]);

        $stream = LiveStream::create([
            'workspace_id' => $workspace->id,
            'title' => '7/24 Test Yayını',
            'source_type' => 'pool',
            'play_order' => 'sequential',
            'aspect_ratio_filter' => 'all',
            'resolution_mode' => 'vertical_1080p',
            'youtube_enabled' => true,
            'youtube_rtmp_url' => 'rtmp://a.rtmp.youtube.com/live2',
            'youtube_stream_key' => 'yt-secret-key-123',
            'instagram_enabled' => false,
        ]);

        $this->assertEquals(2, $stream->getEligibleVideos()->count());
        $this->assertEquals(40, $stream->getTotalLoopDurationSeconds());

        $service = app(LiveStreamService::class);
        $concatFile = $service->buildConcatFile($stream);

        $this->assertFileExists($concatFile);
        $content = file_get_contents($concatFile);
        $this->assertStringContainsString('ffconcat version 1.0', $content);
        $this->assertStringContainsString('videos/v1.mp4', $content);
        $this->assertStringContainsString('videos/v2.mp4', $content);
    }

    public function test_builds_simulcast_ffmpeg_command_for_youtube_and_instagram(): void
    {
        $workspace = Workspace::create(['name' => 'Test WS', 'slug' => 'test-ws']);
        Storage::disk('public')->put('videos/sample.mp4', 'fake-mp4-data');

        Video::create([
            'workspace_id' => $workspace->id,
            'title' => '1. Video',
            'original_filename' => 'sample.mp4',
            'storage_path' => 'videos/sample.mp4',
            'disk' => 'public',
            'file_hash' => md5('sample'),
            'file_size' => 1024,
            'duration_seconds' => 30,
            'width' => 1080,
            'height' => 1920,
            'aspect_ratio' => '9:16',
            'status' => VideoStatus::READY,
        ]);

        $stream = LiveStream::create([
            'workspace_id' => $workspace->id,
            'title' => 'YouTube + Instagram Simulcast',
            'source_type' => 'pool',
            'play_order' => 'sequential',
            'resolution_mode' => 'vertical_1080p',
            'bitrate_kbps' => 2500,
            'fps' => 30,
            'is_loop' => true,
            'youtube_enabled' => true,
            'youtube_rtmp_url' => 'rtmp://a.rtmp.youtube.com/live2',
            'youtube_stream_key' => 'yt-key-999',
            'instagram_enabled' => true,
            'instagram_rtmp_url' => 'rtmps://edgetee-upload-iad.xx.fbcdn.net:443/rtmp/',
            'instagram_stream_key' => 'ig-key-888',
        ]);

        $service = app(LiveStreamService::class);
        $cmd = $service->buildFfmpegCommand($stream);

        $this->assertStringContainsString('-stream_loop -1', $cmd);
        $this->assertStringContainsString('-f tee', $cmd);
        $this->assertStringContainsString('rtmp://a.rtmp.youtube.com/live2/yt-key-999', $cmd);
        $this->assertStringContainsString('rtmps://edgetee-upload-iad.xx.fbcdn.net:443/rtmp/ig-key-888', $cmd);
        $this->assertStringContainsString('onfail=ignore', $cmd);
    }

    public function test_supports_tiktok_live_and_three_way_simulcast(): void
    {
        $workspace = Workspace::create(['name' => 'Test WS', 'slug' => 'test-ws']);
        Storage::disk('public')->put('videos/sample2.mp4', 'fake-mp4-data');

        Video::create([
            'workspace_id' => $workspace->id,
            'title' => 'Test Video',
            'original_filename' => 'sample2.mp4',
            'storage_path' => 'videos/sample2.mp4',
            'disk' => 'public',
            'file_hash' => md5('sample2'),
            'file_size' => 1024,
            'duration_seconds' => 20,
            'width' => 1080,
            'height' => 1920,
            'aspect_ratio' => '9:16',
            'status' => VideoStatus::READY,
        ]);

        $stream = LiveStream::create([
            'workspace_id' => $workspace->id,
            'title' => '3 Platform Simulcast (YT + IG + TT)',
            'target_platform' => 'multi',
            'source_type' => 'pool',
            'resolution_mode' => 'vertical_1080p',
            'youtube_enabled' => true,
            'youtube_stream_key' => 'yt-123',
            'instagram_enabled' => true,
            'instagram_stream_key' => 'ig-456',
            'tiktok_enabled' => true,
            'tiktok_rtmp_url' => 'rtmp://live-push.tiktok.com/live/',
            'tiktok_stream_key' => 'tt-789',
        ]);

        $endpoints = $stream->getActiveRtmpEndpoints();
        $this->assertArrayHasKey('youtube', $endpoints);
        $this->assertArrayHasKey('instagram', $endpoints);
        $this->assertArrayHasKey('tiktok', $endpoints);
        $this->assertStringContainsString('tt-789', $endpoints['tiktok']);

        $cmd = app(LiveStreamService::class)->buildFfmpegCommand($stream);
        $this->assertStringContainsString('live-push.tiktok.com/live/tt-789', $cmd);
    }

    public function test_provisions_youtube_live_stream_key_from_connected_account(): void
    {
        $workspace = Workspace::create(['name' => 'Test WS', 'slug' => 'test-ws']);
        $platform = SocialPlatform::create([
            'code' => 'youtube',
            'name' => 'YouTube',
            'is_active' => true,
        ]);
        $account = SocialAccount::create([
            'workspace_id' => $workspace->id,
            'platform_id' => $platform->id,
            'account_id' => 'yt_chan_1',
            'name' => 'Test YouTube Kanalı',
            'username' => 'testyt',
            'account_type' => 'channel',
            'status' => 'active',
        ]);
        SocialAccountCredential::create([
            'social_account_id' => $account->id,
            'access_token' => 'partner_oauth_youtube_testtoken',
            'token_type' => 'Bearer',
        ]);

        $stream = LiveStream::create([
            'workspace_id' => $workspace->id,
            'title' => 'Otomatik Anahtar Testi',
            'youtube_enabled' => true,
            'youtube_account_id' => $account->id,
        ]);

        $result = app(LiveStreamService::class)->provisionYouTubeLiveStream($stream);

        $this->assertNotEmpty($result['stream_key']);
        $this->assertNotEmpty($stream->fresh()->youtube_stream_key);
    }

    public function test_audio_track_detection_and_safeguard(): void
    {
        $service = app(LiveStreamService::class);
        $tempFile = sys_get_temp_dir() . '/test_silent_video.mp4';
        file_put_contents($tempFile, 'ftypmp42...fake_video_stream');

        $this->assertFalse($service->hasAudioTrack($tempFile));

        $tempWithAudio = sys_get_temp_dir() . '/test_audio_video.mp4';
        file_put_contents($tempWithAudio, 'ftypmp42...soun...mp4a...');
        $this->assertTrue($service->hasAudioTrack($tempWithAudio));

        @unlink($tempFile);
        @unlink($tempWithAudio);
    }

    public function test_background_music_replace_and_mix_modes(): void
    {
        $workspace = Workspace::create(['name' => 'Test WS', 'slug' => 'test-ws']);
        Storage::disk('public')->put('videos/sample_bg.mp4', 'fake-video-content');
        Storage::disk('public')->put('audio-library/custom_beat.mp3', 'fake-audio-content');

        Video::create([
            'workspace_id' => $workspace->id,
            'title' => 'Bg Test Video',
            'original_filename' => 'sample_bg.mp4',
            'storage_path' => 'videos/sample_bg.mp4',
            'disk' => 'public',
            'file_hash' => md5('sample_bg'),
            'file_size' => 1024,
            'duration_seconds' => 15,
            'width' => 1080,
            'height' => 1920,
            'status' => VideoStatus::READY,
        ]);

        // 1. Replace mode with custom audio file
        $streamReplace = LiveStream::create([
            'workspace_id' => $workspace->id,
            'title' => 'Replace Audio Stream',
            'source_type' => 'pool',
            'youtube_enabled' => true,
            'youtube_stream_key' => 'test-key',
            'audio_mode' => 'replace_bg_music',
            'bg_music_source' => 'custom',
            'bg_music_path' => 'audio-library/custom_beat.mp3',
            'bg_music_volume' => 80,
        ]);

        $service = app(LiveStreamService::class);
        $cmdReplace = $service->buildFfmpegCommand($streamReplace);

        $this->assertStringContainsString('custom_beat.mp3', $cmdReplace);
        $this->assertStringContainsString('-map "[vout]" -map "[aout]"', $cmdReplace);
        $this->assertStringContainsString('volume=0.80', $cmdReplace);

        // 2. Mix mode with custom audio file
        $streamMix = LiveStream::create([
            'workspace_id' => $workspace->id,
            'title' => 'Mix Audio Stream',
            'source_type' => 'pool',
            'youtube_enabled' => true,
            'youtube_stream_key' => 'test-key',
            'audio_mode' => 'mix_bg_music',
            'bg_music_source' => 'custom',
            'bg_music_path' => 'audio-library/custom_beat.mp3',
            'original_audio_volume' => 100,
            'bg_music_volume' => 25,
        ]);

        $cmdMix = $service->buildFfmpegCommand($streamMix);

        $this->assertStringContainsString('-filter_complex', $cmdMix);
        $this->assertStringContainsString('amix=inputs=2', $cmdMix);
        $this->assertStringContainsString('volume=0.25', $cmdMix);
        $this->assertStringContainsString('-map "[vout]" -map "[aout]"', $cmdMix);
    }
}
