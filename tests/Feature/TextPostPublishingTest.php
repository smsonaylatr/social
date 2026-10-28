<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\VideoStatus;
use App\Jobs\DispatchPlatformPostJob;
use App\Models\Brand;
use App\Models\PublicationAttempt;
use App\Models\ScheduledPost;
use App\Models\ScheduledPostTarget;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\SocialAccountCredential;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\Social\Publishers\FacebookPublisher;
use App\Services\Social\Publishers\LinkedInPublisher;
use App\Services\Social\Publishers\XPublisher;
use Database\Seeders\InitialSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TextPostPublishingTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected Brand $brand;
    protected User $admin;
    protected Video $video;

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
            'title' => 'Patenli Spor Ayakkabı Lansman',
            'custom_caption' => "Adım at, topuğa bas ve kaymaya başla! 🚀\n\n✨ Tek hareketle patene dönüşen ayakkabılar\n👟 Işıklı ve gizli tekerlekli modeller",
            'original_filename' => 'lansman.mp4',
            'storage_path' => 'videos/lansman.mp4',
            'file_hash' => hash('sha256', 'lansman_hash'),
            'file_size' => 1500000,
            'duration_seconds' => 20,
            'aspect_ratio' => '9:16',
            'status' => VideoStatus::READY,
        ]);
    }

    protected function createAccount(string $platformCode, string $accountId, string $name): SocialAccount
    {
        $platform = SocialPlatform::where('code', $platformCode)->first();

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
            'access_token' => 'mock_token_' . $platformCode,
            'refresh_token' => 'mock_refresh_' . $platformCode,
        ]);

        return $account;
    }

    public function test_x_publishes_as_text_post_without_video_by_default(): void
    {
        $account = $this->createAccount('x', 'x_user_99', 'Patenli X');

        $scheduledPost = ScheduledPost::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'video_id' => $this->video->id,
            'scheduled_at' => now(),
            'status' => PublishStatus::SCHEDULED,
        ]);

        $target = ScheduledPostTarget::create([
            'scheduled_post_id' => $scheduledPost->id,
            'social_account_id' => $account->id,
            'platform_code' => 'x',
            'scheduled_at' => now(),
            'correlation_id' => 'corr_x_test_' . uniqid(),
            'idempotency_key' => 'idem_x_test_' . uniqid(),
            'status' => PublishStatus::SCHEDULED,
            'caption' => "Patenli ayakkabı modelleriyle sokaklar artık bir pist! 👟✨\n\n🌐 https://patenliayakkabilar.com\n#PatenliAyakkabı #TekerlekliAyakkabı",
        ]);

        $publisher = app(XPublisher::class);
        $result = $publisher->publish($this->video, $target, $account);

        $this->assertEquals('published', $result['status']);
        $this->assertEquals('text', $result['post_type']);
        $this->assertNotEmpty($result['post_id']);
        $this->assertStringContainsString('x.com', $result['post_url']);
    }

    public function test_linkedin_publishes_as_text_post_without_video_by_default(): void
    {
        $account = $this->createAccount('linkedin', 'li_user_99', 'Patenli LinkedIn');

        $scheduledPost = ScheduledPost::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'video_id' => $this->video->id,
            'scheduled_at' => now(),
            'status' => PublishStatus::SCHEDULED,
        ]);

        $target = ScheduledPostTarget::create([
            'scheduled_post_id' => $scheduledPost->id,
            'social_account_id' => $account->id,
            'platform_code' => 'linkedin',
            'scheduled_at' => now(),
            'correlation_id' => 'corr_li_test_' . uniqid(),
            'idempotency_key' => 'idem_li_test_' . uniqid(),
            'status' => PublishStatus::SCHEDULED,
            'caption' => "Çocuk patenli ayakkabı ve tekerlekli ayakkabı modellerimizle hareketi eğlenceye dönüştürüyoruz! ⚡\n\n🌐 patenliayakkabilar.com",
        ]);

        $publisher = app(LinkedInPublisher::class);
        $result = $publisher->publish($this->video, $target, $account);

        $this->assertEquals('published', $result['status']);
        $this->assertEquals('text', $result['post_type']);
        $this->assertNotEmpty($result['post_id']);
        $this->assertStringContainsString('linkedin.com/feed/update', $result['post_url']);
    }

    public function test_facebook_publishes_as_text_post_to_feed_without_video(): void
    {
        $account = $this->createAccount('facebook', 'fb_page_99', 'Patenli Facebook Page');

        $scheduledPost = ScheduledPost::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'video_id' => $this->video->id,
            'scheduled_at' => now(),
            'status' => PublishStatus::SCHEDULED,
        ]);

        $target = ScheduledPostTarget::create([
            'scheduled_post_id' => $scheduledPost->id,
            'social_account_id' => $account->id,
            'platform_code' => 'facebook',
            'scheduled_at' => now(),
            'correlation_id' => 'corr_fb_test_' . uniqid(),
            'idempotency_key' => 'idem_fb_test_' . uniqid(),
            'status' => PublishStatus::SCHEDULED,
            'title' => 'Patenli Ayakkabı Modelleri',
            'caption' => "Adım at, topuğa bas ve kaymaya başla! 🚀\n\n✨ Tekerlekli spor ayakkabı modelleri\n🌐 patenliayakkabilar.com",
        ]);

        $publisher = app(FacebookPublisher::class);
        $result = $publisher->publish($this->video, $target, $account);

        $this->assertEquals('published', $result['status']);
        $this->assertEquals('text', $result['post_type']);
        $this->assertNotEmpty($result['post_id']);
        $this->assertStringContainsString('facebook.com', $result['post_url']);
        $this->assertStringNotContainsString('/videos/', $result['post_url']);
    }

    public function test_dispatch_platform_post_job_handles_text_posts_and_logs_summary(): void
    {
        $account = $this->createAccount('facebook', 'fb_page_555', 'Patenli FB Test');

        $scheduledPost = ScheduledPost::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'video_id' => $this->video->id,
            'scheduled_at' => now(),
            'status' => PublishStatus::SCHEDULED,
        ]);

        $target = ScheduledPostTarget::create([
            'scheduled_post_id' => $scheduledPost->id,
            'social_account_id' => $account->id,
            'platform_code' => 'facebook',
            'scheduled_at' => now(),
            'correlation_id' => 'corr_job_fb_' . uniqid(),
            'idempotency_key' => 'idem_job_fb_' . uniqid(),
            'status' => PublishStatus::QUEUED,
            'caption' => 'Yapay zeka tarafından üretilen açıklama videosuz paylaşılıyor! ✨',
        ]);

        DispatchPlatformPostJob::dispatchSync($target->id);

        $target->refresh();
        $this->assertEquals(PublishStatus::PUBLISHED, $target->status);
        $this->assertNotNull($target->published_at);
        $this->assertNotNull($target->external_post_id);

        $attempt = PublicationAttempt::where('scheduled_post_target_id', $target->id)->first();
        $this->assertNotNull($attempt);
        $this->assertEquals('success', $attempt->status);
        $this->assertStringContainsString('Text Post (Videosuz)', $attempt->request_payload_summary);
    }
}
