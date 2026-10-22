<?php

namespace Tests\Feature;

use App\Enums\PlaylistLoopMode;
use App\Enums\PublishStatus;
use App\Enums\VideoStatus;
use App\Models\Brand;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\ScheduledPost;
use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\Playlist\CircularPlaylistEngine;
use Database\Seeders\InitialSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CircularPlaylistEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected Brand $brand;
    protected User $admin;
    protected CircularPlaylistEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSystemSeeder::class);

        $this->workspace = Workspace::where('slug', 'default-workspace')->first();
        $this->brand = Brand::where('slug', 'patenli-ayakkabilar')->first();
        $this->admin = User::where('email', 'admin@socialflow.ai')->first();
        $this->engine = app(CircularPlaylistEngine::class);
    }

    public function test_sequential_playlist_progression(): void
    {
        $playlist = Playlist::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'name' => 'Test Sıralı Liste',
            'slug' => 'test-sirali-liste',
            'loop_mode' => PlaylistLoopMode::SEQUENTIAL,
            'is_circular' => true,
            'cooldown_days' => 7,
        ]);

        $v1 = $this->createTestVideo('Video 1');
        $v2 = $this->createTestVideo('Video 2');
        $v3 = $this->createTestVideo('Video 3');

        PlaylistItem::create(['playlist_id' => $playlist->id, 'video_id' => $v1->id, 'sort_order' => 1]);
        PlaylistItem::create(['playlist_id' => $playlist->id, 'video_id' => $v2->id, 'sort_order' => 2]);
        PlaylistItem::create(['playlist_id' => $playlist->id, 'video_id' => $v3->id, 'sort_order' => 3]);

        // 1st dispatch
        $next1 = $this->engine->getNextVideo($playlist);
        $this->assertEquals($v1->id, $next1->id);
        $this->assertEquals(1, $playlist->cursor->fresh()->current_index);

        // 2nd dispatch
        $next2 = $this->engine->getNextVideo($playlist);
        $this->assertEquals($v2->id, $next2->id);
        $this->assertEquals(2, $playlist->cursor->fresh()->current_index);

        // 3rd dispatch
        $next3 = $this->engine->getNextVideo($playlist);
        $this->assertEquals($v3->id, $next3->id);
    }

    public function test_circular_loop_returns_to_start_when_allowed(): void
    {
        $playlist = Playlist::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'name' => 'Döngü Testi',
            'slug' => 'dongu-testi',
            'loop_mode' => PlaylistLoopMode::SEQUENTIAL,
            'is_circular' => true,
            'cooldown_days' => 0, // Zero cooldown for testing circular return
        ]);

        $v1 = $this->createTestVideo('Döngü Video 1');
        $v2 = $this->createTestVideo('Döngü Video 2');

        PlaylistItem::create(['playlist_id' => $playlist->id, 'video_id' => $v1->id, 'sort_order' => 1]);
        PlaylistItem::create(['playlist_id' => $playlist->id, 'video_id' => $v2->id, 'sort_order' => 2]);

        $this->engine->getNextVideo($playlist); // returns v1
        $this->engine->getNextVideo($playlist); // returns v2, cycle completes

        // 3rd dispatch should loop back to v1
        $looped = $this->engine->getNextVideo($playlist);
        $this->assertEquals($v1->id, $looped->id);
        $this->assertGreaterThanOrEqual(1, $playlist->cursor->fresh()->cycle_count);
    }

    public function test_cooldown_skips_recently_used_video(): void
    {
        $playlist = Playlist::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'name' => 'Cooldown Testi',
            'slug' => 'cooldown-testi',
            'loop_mode' => PlaylistLoopMode::SEQUENTIAL,
            'is_circular' => true,
            'cooldown_days' => 14,
        ]);

        // Video 1 was used 2 days ago (violates 14-day cooldown)
        $v1 = $this->createTestVideo('Yeni Kullanılmış Video', [
            'usage_count' => 1,
            'last_used_at' => now()->subDays(2),
        ]);

        // Video 2 was never used
        $v2 = $this->createTestVideo('Kullanılmamış Video', [
            'usage_count' => 0,
            'last_used_at' => null,
        ]);

        PlaylistItem::create(['playlist_id' => $playlist->id, 'video_id' => $v1->id, 'sort_order' => 1]);
        PlaylistItem::create(['playlist_id' => $playlist->id, 'video_id' => $v2->id, 'sort_order' => 2]);

        // Should skip v1 and pick v2
        $selected = $this->engine->getNextVideo($playlist);
        $this->assertEquals($v2->id, $selected->id);
    }

    public function test_youtube_duplicate_policy_blocks_reupload(): void
    {
        $playlist = Playlist::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'name' => 'YouTube Politika Testi',
            'slug' => 'yt-policy-test',
            'loop_mode' => PlaylistLoopMode::SEQUENTIAL,
            'cooldown_days' => 0,
        ]);

        $v1 = $this->createTestVideo('YouTube Yayını Olan Video');
        $v2 = $this->createTestVideo('YouTube İçin Taze Video');

        PlaylistItem::create(['playlist_id' => $playlist->id, 'video_id' => $v1->id, 'sort_order' => 1]);
        PlaylistItem::create(['playlist_id' => $playlist->id, 'video_id' => $v2->id, 'sort_order' => 2]);

        $ytPlatform = SocialPlatform::where('code', 'youtube')->first();
        $ytAccount = SocialAccount::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'platform_id' => $ytPlatform->id,
            'account_id' => 'yt_channel_123',
            'name' => 'Patenli Ayakkabılar YouTube',
            'status' => 'active',
        ]);

        // Simulate that v1 was previously published on YouTube
        $scheduledPost = ScheduledPost::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'video_id' => $v1->id,
            'scheduled_at' => now()->subDays(5),
            'status' => PublishStatus::PUBLISHED,
        ]);

        ScheduledPostTarget::create([
            'scheduled_post_id' => $scheduledPost->id,
            'social_account_id' => $ytAccount->id,
            'platform_code' => 'youtube',
            'correlation_id' => 'corr_1',
            'idempotency_key' => 'idemp_1',
            'status' => PublishStatus::PUBLISHED,
            'published_at' => now()->subDays(5),
        ]);

        // When asking for next video specifically for YouTube, v1 must be skipped!
        $selectedForYt = $this->engine->getNextVideo($playlist, 'youtube');
        $this->assertEquals($v2->id, $selectedForYt->id);
    }

    public function test_admin_can_access_playlist_resource(): void
    {
        $playlist = Playlist::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'name' => 'Panel Listesi',
            'slug' => 'panel-listesi',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/playlists');
        $response->assertStatus(200);
        $response->assertSee('Panel Listesi');
    }

    protected function createTestVideo(string $title, array $overrides = []): Video
    {
        return Video::create(array_merge([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'title' => $title,
            'original_filename' => 'sample.mp4',
            'storage_path' => 'videos/sample.mp4',
            'file_hash' => hash('sha256', $title . time() . uniqid()),
            'file_size' => 1048576,
            'duration_seconds' => 30,
            'aspect_ratio' => '9:16',
            'status' => VideoStatus::READY,
            'is_reusable' => true,
        ], $overrides));
    }
}
