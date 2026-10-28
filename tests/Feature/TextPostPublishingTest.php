<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\VideoStatus;
use App\Jobs\DispatchPlatformPostJob;
use App\Models\Brand;
use App\Models\PublicationAttempt;
use App\Models\Schedule;
use App\Models\ScheduledPost;
use App\Models\ScheduledPostTarget;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\SocialAccountCredential;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\Publisher\SchedulingService;
use App\Services\Social\Publishers\FacebookPublisher;
use App\Services\Social\Publishers\LinkedInPublisher;
use App\Services\Social\Publishers\XPublisher;
use Database\Seeders\InitialSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_existing_automation_rules_remain_video_by_default(): void
    {
        $schedule = Schedule::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'name' => 'Mevcut Video Kuralı',
            'schedule_type' => 'algorithmic_growth',
            'post_type' => 'video',
            'is_active' => true,
        ]);

        $this->assertEquals('video', $schedule->post_type);

        $account = $this->createAccount('x', 'x_acc_video', 'Patenli X Video');

        $service = app(SchedulingService::class);
        $post = $service->createScheduledPost(
            video: $this->video,
            scheduledAt: now(),
            socialAccountIds: [$account->id],
            scheduleId: $schedule->id
        );

        $this->assertEquals('video', $post->post_type);
        $this->assertEquals('video', $post->targets->first()->post_type);

        $publisher = app(XPublisher::class);
        $result = $publisher->publish($this->video, $post->targets->first(), $account);

        $this->assertEquals('video', $result['post_type']);
    }

    public function test_new_automation_rule_can_be_configured_as_text_post(): void
    {
        $textSchedule = Schedule::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'name' => 'Yeni X ve LinkedIn Metin Otomasyonu',
            'schedule_type' => 'algorithmic_growth',
            'post_type' => 'text',
            'is_active' => true,
        ]);

        $this->assertEquals('text', $textSchedule->post_type);

        $accountX = $this->createAccount('x', 'x_acc_text', 'Patenli X Metin');
        $accountLi = $this->createAccount('linkedin', 'li_acc_text', 'Patenli LinkedIn Metin');
        $accountFb = $this->createAccount('facebook', 'fb_acc_text', 'Patenli Facebook Metin');

        $service = app(SchedulingService::class);
        $post = $service->createScheduledPost(
            video: $this->video,
            scheduledAt: now(),
            socialAccountIds: [$accountX->id, $accountLi->id, $accountFb->id],
            scheduleId: $textSchedule->id
        );

        $this->assertEquals('text', $post->post_type);

        foreach ($post->targets as $target) {
            $this->assertEquals('text', $target->post_type);
        }

        $xResult = app(XPublisher::class)->publish($this->video, $post->targets->firstWhere('platform_code', 'x'), $accountX);
        $this->assertEquals('text', $xResult['post_type']);

        $liResult = app(LinkedInPublisher::class)->publish($this->video, $post->targets->firstWhere('platform_code', 'linkedin'), $accountLi);
        $this->assertEquals('text', $liResult['post_type']);

        $fbResult = app(FacebookPublisher::class)->publish($this->video, $post->targets->firstWhere('platform_code', 'facebook'), $accountFb);
        $this->assertEquals('text', $fbResult['post_type']);
        $this->assertStringNotContainsString('/videos/', $fbResult['post_url']);
    }

    public function test_dispatch_platform_post_job_records_text_or_video_summary(): void
    {
        $account = $this->createAccount('facebook', 'fb_page_job', 'Patenli FB Job');

        $post = ScheduledPost::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'video_id' => $this->video->id,
            'post_type' => 'text',
            'scheduled_at' => now(),
            'status' => PublishStatus::SCHEDULED,
        ]);

        $target = ScheduledPostTarget::create([
            'scheduled_post_id' => $post->id,
            'social_account_id' => $account->id,
            'platform_code' => 'facebook',
            'post_type' => 'text',
            'scheduled_at' => now(),
            'correlation_id' => 'corr_job_text_' . uniqid(),
            'idempotency_key' => 'idem_job_text_' . uniqid(),
            'status' => PublishStatus::QUEUED,
            'caption' => 'Yapay zeka tarafından üretilen açıklama videosuz paylaşılıyor! ✨',
        ]);

        DispatchPlatformPostJob::dispatchSync($target->id);

        $target->refresh();
        $this->assertEquals(PublishStatus::PUBLISHED, $target->status);

        $attempt = PublicationAttempt::where('scheduled_post_target_id', $target->id)->first();
        $this->assertNotNull($attempt);
        $this->assertEquals('success', $attempt->status);
        $this->assertStringContainsString('Text Post (Videosuz)', $attempt->request_payload_summary);
    }

    public function test_text_posts_never_contain_emojis(): void
    {
        $sampleTextWithEmojis = "Adım at, topuğa bas ve kaymaya başla! 🚀\n\n✨ Tek hareketle patene dönüşen ayakkabılar\n👟 Işıklı ve gizli tekerlekli modeller 🎁\n🚚 Hızlı kargo • 🛍️ Güvenli alışveriş";
        $cleaned = \App\Services\AI\OpenAiContentService::stripEmojis($sampleTextWithEmojis);

        $this->assertStringNotContainsString('🚀', $cleaned);
        $this->assertStringNotContainsString('✨', $cleaned);
        $this->assertStringNotContainsString('👟', $cleaned);
        $this->assertStringNotContainsString('🎁', $cleaned);
        $this->assertStringNotContainsString('🚚', $cleaned);
        $this->assertStringNotContainsString('🛍️', $cleaned);
        $this->assertStringContainsString('Adım at, topuğa bas ve kaymaya başla!', $cleaned);

        // Schedule as text post and check target captions
        $textSchedule = Schedule::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'name' => 'Emojissiz Metin Kuralı',
            'post_type' => 'text',
            'is_active' => true,
        ]);

        $account = $this->createAccount('x', 'x_acc_no_emoji', 'Patenli X No Emoji');
        $service = app(SchedulingService::class);
        $post = $service->createScheduledPost(
            video: $this->video,
            scheduledAt: now(),
            socialAccountIds: [$account->id],
            scheduleId: $textSchedule->id
        );

        $target = $post->targets->first();
        $this->assertEquals('text', $target->post_type);
        $this->assertStringNotContainsString('✨', $target->caption);
        $this->assertStringNotContainsString('🚀', $target->caption);
        $this->assertStringNotContainsString('👟', $target->caption);
    }
}
