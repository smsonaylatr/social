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
}
