<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\SocialPlatformCode;
use App\Enums\VideoStatus;
use App\Jobs\DispatchPlatformPostJob;
use App\Models\Brand;
use App\Models\PublicationAttempt;
use App\Models\ScheduledPost;
use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\SocialAccountCredential;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\Publisher\SchedulingService;
use App\Services\Social\SocialPublisherManager;
use Database\Seeders\InitialSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishingAndSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected Brand $brand;
    protected User $admin;
    protected Video $video;
    protected SocialAccount $fbAccount;
    protected SocialAccount $igAccount;
    protected SocialAccount $ytAccount;
    protected SocialAccount $xAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSystemSeeder::class);

        $this->workspace = Workspace::where('slug', 'default-workspace')->first();
        $this->brand = Brand::where('slug', 'patenli-ayakkabilar')->first();
        $this->admin = User::where('email', 'admin@socialflow.ai')->first();

        $this->video = Video::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'title' => 'Eş Zamanlı Yayın Test Videosu',
            'original_filename' => 'sync_video.mp4',
            'storage_path' => 'videos/sync_video.mp4',
            'file_hash' => hash('sha256', 'sync_video'),
            'file_size' => 2000000,
            'duration_seconds' => 30,
            'aspect_ratio' => '9:16',
            'status' => VideoStatus::READY,
        ]);

        // Create test accounts for Facebook, Instagram, YouTube, X
        $this->fbAccount = $this->createAccount('facebook', 'fb_page_100', 'Patenli Facebook');
        $this->igAccount = $this->createAccount('instagram', 'ig_user_200', 'Patenli Instagram');
        $this->ytAccount = $this->createAccount('youtube', 'yt_channel_300', 'Patenli YouTube');
        $this->xAccount = $this->createAccount('x', 'x_user_400', 'Patenli X');
    }

    protected function createAccount(string $code, string $accountId, string $name): SocialAccount
    {
        $platform = SocialPlatform::where('code', $code)->first();
        $account = SocialAccount::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'platform_id' => $platform->id,
            'account_id' => $accountId,
            'name' => $name,
            'username' => strtolower(str_replace(' ', '_', $name)),
            'status' => 'active',
        ]);

        SocialAccountCredential::create([
            'social_account_id' => $account->id,
            'access_token' => 'mock_token_' . $code,
            'refresh_token' => 'mock_refresh_' . $code,
        ]);

        return $account;
    }

    public function test_scheduling_service_creates_multi_target_post(): void
    {
        $service = app(SchedulingService::class);
        $scheduledAt = now()->addHour();

        $accountIds = [
            $this->fbAccount->id,
            $this->igAccount->id,
            $this->ytAccount->id,
            $this->xAccount->id,
        ];

        $post = $service->createScheduledPost(
            video: $this->video,
            scheduledAt: $scheduledAt,
            socialAccountIds: $accountIds
        );

        $this->assertNotNull($post);
        $this->assertEquals(PublishStatus::SCHEDULED, $post->status);
        $this->assertEquals(4, $post->targets()->count());

        foreach ($post->targets as $target) {
            $this->assertNotEmpty($target->correlation_id);
            $this->assertNotEmpty($target->idempotency_key);
            $this->assertNotEmpty($target->caption);
            $this->assertEquals(PublishStatus::SCHEDULED, $target->status);
        }
    }

    public function test_dispatch_platform_post_job_publishes_and_enforces_idempotency(): void
    {
        $service = app(SchedulingService::class);
        $post = $service->createScheduledPost(
            video: $this->video,
            scheduledAt: now(),
            socialAccountIds: [$this->fbAccount->id, $this->ytAccount->id]
        );

        $fbTarget = $post->targets()->where('platform_code', 'facebook')->first();
        $this->assertNotNull($fbTarget);

        // Run Job directly
        $manager = app(SocialPublisherManager::class);
        $job = new DispatchPlatformPostJob($fbTarget->id);
        $job->handle($manager);

        $fbTarget->refresh();
        $this->assertEquals(PublishStatus::PUBLISHED, $fbTarget->status);
        $this->assertNotNull($fbTarget->external_post_id);
        $this->assertNotNull($fbTarget->published_at);
        $this->assertEquals(1, $fbTarget->attempt_count);

        // Publication attempt recorded
        $attempt = PublicationAttempt::where('scheduled_post_target_id', $fbTarget->id)->first();
        $this->assertNotNull($attempt);
        $this->assertEquals('success', $attempt->status);

        // Idempotency test: Re-running the job must NOT create a second attempt
        $job->handle($manager);
        $this->assertEquals(1, PublicationAttempt::where('scheduled_post_target_id', $fbTarget->id)->count());
    }

    public function test_social_publisher_manager_resolves_all_seven_platforms(): void
    {
        $manager = app(SocialPublisherManager::class);
        $platforms = ['facebook', 'instagram', 'youtube', 'x', 'pinterest', 'tiktok', 'linkedin'];

        foreach ($platforms as $platform) {
            $publisher = $manager->getPublisher($platform);
            $this->assertNotNull($publisher);
            $this->assertEquals($platform, $publisher->getPlatformCode());
        }
    }

    public function test_admin_can_access_scheduling_and_social_accounts_resources(): void
    {
        $responseSchedules = $this->actingAs($this->admin)->get('/admin/schedules');
        $responseSchedules->assertStatus(200);

        $responsePosts = $this->actingAs($this->admin)->get('/admin/scheduled-posts');
        $responsePosts->assertStatus(200);

        $responseAccounts = $this->actingAs($this->admin)->get('/admin/social-accounts');
        $responseAccounts->assertStatus(200);
        $responseAccounts->assertSee('Hesaplar & Yetkilendirme');
    }

    public function test_requeue_failed_targets_updates_post_status(): void
    {
        $service = app(SchedulingService::class);
        $post = $service->createScheduledPost(
            video: $this->video,
            scheduledAt: now(),
            socialAccountIds: [$this->fbAccount->id, $this->ytAccount->id]
        );

        // FB yayınlandı, YouTube ise limitExceeded ile başarısız oldu
        $fbTarget = $post->targets()->where('platform_code', 'facebook')->first();
        $ytTarget = $post->targets()->where('platform_code', 'youtube')->first();

        $fbTarget->update(['status' => PublishStatus::PUBLISHED]);
        $ytTarget->update([
            'status' => PublishStatus::FAILED,
            'error_message' => 'uploadLimitExceeded: quota exceeded'
        ]);

        $post->updateStatusBasedOnTargets();
        $this->assertEquals(PublishStatus::PARTIALLY_PUBLISHED, $post->fresh()->status);

        // Şimdi başarısız hedefleri yeniden kuyruğa alalım
        $requeuedCount = $service->requeueFailedTargets($post->id);

        $this->assertEquals(1, $requeuedCount);
        $this->assertEquals(PublishStatus::QUEUED, $ytTarget->fresh()->status);
        $this->assertEquals(0, $ytTarget->fresh()->attempt_count);
        $this->assertEquals(PublishStatus::QUEUED, $post->fresh()->status);
    }
}
