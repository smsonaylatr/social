<?php

namespace Tests\Feature;

use App\Enums\PlaylistLoopMode;
use App\Enums\PublishStatus;
use App\Enums\SocialPlatformCode;
use App\Enums\VideoStatus;
use App\Models\Brand;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Schedule;
use App\Models\ScheduledPost;
use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\SocialAccountCredential;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\Automation\AutomationOrchestratorService;
use App\Services\Social\SocialPublisherManager;
use Database\Seeders\InitialSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndToEndAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected Brand $brand;
    protected User $admin;
    protected array $socialAccounts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSystemSeeder::class);

        $this->workspace = Workspace::where('slug', 'default-workspace')->first();
        $this->brand = Brand::where('slug', 'patenli-ayakkabilar')->first();
        $this->admin = User::where('email', 'admin@socialflow.ai')->first();

        // Connect 7 official social platforms
        $platforms = ['facebook', 'instagram', 'youtube', 'x', 'pinterest', 'tiktok', 'linkedin'];
        foreach ($platforms as $pCode) {
            $p = SocialPlatform::where('code', $pCode)->first();
            $acc = SocialAccount::create([
                'workspace_id' => $this->workspace->id,
                'brand_id' => $this->brand->id,
                'platform_id' => $p->id,
                'account_id' => "acc_{$pCode}_123",
                'name' => "Patenli {$p->name}",
                'username' => "patenli_{$pCode}",
                'status' => 'active',
            ]);

            SocialAccountCredential::create([
                'social_account_id' => $acc->id,
                'access_token' => "mock_token_{$pCode}",
                'refresh_token' => "mock_refresh_{$pCode}",
            ]);

            $this->socialAccounts[$pCode] = $acc;
        }
    }

    public function test_complete_end_to_end_automation_lifecycle(): void
    {
        // 1. Create a circular playlist
        $playlist = Playlist::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'name' => 'Patenli Ayakkabılar Günlük Video Paylaşımı',
            'slug' => 'patenli-gunluk-paylasim',
            'loop_mode' => PlaylistLoopMode::SEQUENTIAL,
            'is_circular' => true,
            'cooldown_days' => 7,
        ]);

        // 2. Add multiple videos to the playlist
        $videos = [];
        for ($i = 1; $i <= 5; $i++) {
            $video = Video::create([
                'workspace_id' => $this->workspace->id,
                'brand_id' => $this->brand->id,
                'title' => "Patenli Ayakkabı Video #{$i}",
                'original_filename' => "video_{$i}.mp4",
                'storage_path' => "videos/video_{$i}.mp4",
                'file_hash' => hash('sha256', "video_{$i}"),
                'file_size' => 2048000,
                'duration_seconds' => 30,
                'aspect_ratio' => '9:16',
                'status' => VideoStatus::READY,
                'is_reusable' => true,
            ]);
            $videos[] = $video;

            PlaylistItem::create([
                'playlist_id' => $playlist->id,
                'video_id' => $video->id,
                'sort_order' => $i,
            ]);
        }

        // 3. Create automation schedule for 12:00 with all 7 accounts
        $accountIds = array_map(fn ($acc) => $acc->id, $this->socialAccounts);

        $schedule = Schedule::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'playlist_id' => $playlist->id,
            'name' => 'Günlük 12:00 Otomasyonu',
            'time_of_day' => '12:00:00',
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
            'target_social_account_ids' => $accountIds,
            'max_posts_per_day' => 3,
            'is_active' => true,
        ]);

        // 4. Run orchestrator
        $orchestrator = app(AutomationOrchestratorService::class);
        $scheduledPost = $orchestrator->runSchedule($schedule);

        $this->assertNotNull($scheduledPost);
        $this->assertEquals($videos[0]->id, $scheduledPost->video_id);

        // 5. Verify 7 platform targets created
        $this->assertEquals(7, $scheduledPost->targets()->count());

        // 6. Simulate worker execution on all 7 targets
        $publisherManager = app(SocialPublisherManager::class);
        foreach ($scheduledPost->targets as $target) {
            $job = new \App\Jobs\DispatchPlatformPostJob($target->id);
            $job->handle($publisherManager);

            $target->refresh();
            $this->assertEquals(PublishStatus::PUBLISHED, $target->status);
            $this->assertNotNull($target->external_post_id);
            $this->assertNotNull($target->external_post_url);
        }

        // 7. Verify parent post status is PUBLISHED
        $scheduledPost->refresh();
        $this->assertEquals(PublishStatus::PUBLISHED, $scheduledPost->status);

        // 8. Verify video usage count incremented (7 platforms published = 7 usages)
        $videos[0]->refresh();
        $this->assertEquals(7, $videos[0]->usage_count);
        $this->assertNotNull($videos[0]->last_used_at);

        // 9. Verify second run advances to video 2
        $scheduledPost2 = $orchestrator->runSchedule($schedule);
        $this->assertNotNull($scheduledPost2);
        $this->assertEquals($videos[1]->id, $scheduledPost2->video_id);
    }
}
