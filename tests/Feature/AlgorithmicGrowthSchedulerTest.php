<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\SocialPlatformCode;
use App\Enums\VideoStatus;
use App\Models\Brand;
use App\Models\Schedule;
use App\Models\ScheduledPost;
use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\Publisher\SchedulingService;
use App\Services\Scheduling\AlgorithmicGrowthSchedulerService;
use Database\Seeders\InitialSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlgorithmicGrowthSchedulerTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected Brand $brand;
    protected Video $video;
    protected SocialAccount $igAccount;
    protected SocialAccount $ttAccount;
    protected SocialAccount $ytAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSystemSeeder::class);

        $this->workspace = Workspace::where('slug', 'default-workspace')->first();
        $this->brand = Brand::where('slug', 'patenli-ayakkabilar')->first();

        $this->video = Video::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'title' => 'Algoritmik Test Videosu',
            'original_filename' => 'algo_test.mp4',
            'storage_path' => 'videos/algo_test.mp4',
            'file_hash' => hash('sha256', 'algo_test'),
            'file_size' => 1500000,
            'duration_seconds' => 25,
            'aspect_ratio' => '9:16',
            'status' => VideoStatus::READY,
        ]);

        $igPlatform = SocialPlatform::where('code', SocialPlatformCode::INSTAGRAM->value)->first();
        $ttPlatform = SocialPlatform::where('code', SocialPlatformCode::TIKTOK->value)->first();
        $ytPlatform = SocialPlatform::where('code', SocialPlatformCode::YOUTUBE->value)->first();

        $this->igAccount = SocialAccount::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'platform_id' => $igPlatform->id,
            'account_type' => 'business',
            'account_id' => 'ig_algo_1',
            'name' => 'Instagram Algo Test',
            'status' => 'active',
        ]);

        $this->ttAccount = SocialAccount::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'platform_id' => $ttPlatform->id,
            'account_type' => 'business',
            'account_id' => 'tt_algo_1',
            'name' => 'TikTok Algo Test',
            'status' => 'active',
        ]);

        $this->ytAccount = SocialAccount::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'platform_id' => $ytPlatform->id,
            'account_type' => 'channel',
            'account_id' => 'yt_algo_1',
            'name' => 'YouTube Algo Test',
            'status' => 'active',
        ]);
    }

    public function test_all_seven_platforms_have_algorithmic_profiles(): void
    {
        $service = app(AlgorithmicGrowthSchedulerService::class);
        $profiles = $service->getAllProfiles();

        $expectedPlatforms = ['instagram', 'tiktok', 'youtube', 'facebook', 'x', 'pinterest', 'linkedin'];
        foreach ($expectedPlatforms as $platform) {
            $this->assertArrayHasKey($platform, $profiles);
            $profile = $profiles[$platform];

            $this->assertNotEmpty($profile->peakSlotsWeekday, "{$platform} weekday peak slots must not be empty");
            $this->assertNotEmpty($profile->peakSlotsWeekend, "{$platform} weekend peak slots must not be empty");
            $this->assertGreaterThan(0, $profile->minCooldownMinutes, "{$platform} cooldown must be greater than 0");
            $this->assertNotEmpty($profile->algorithmTip, "{$platform} must have an algorithm tip");
        }
    }

    public function test_staggered_target_times_prevent_simultaneous_posting(): void
    {
        $service = app(AlgorithmicGrowthSchedulerService::class);
        $accounts = [$this->igAccount, $this->ttAccount, $this->ytAccount];
        $baseTime = now()->setTime(18, 0, 0);

        $targetTimes = $service->generateStaggeredTargetTimes(
            accounts: $accounts,
            baseTime: $baseTime,
            enableJitter: false,
            enableStagger: true
        );

        $this->assertCount(3, $targetTimes);

        // Instagram önceliklidir (stagger 0), TikTok 2. sıradadır (+6-12 dk), YouTube 3. sıradadır
        $igTime = $targetTimes[$this->igAccount->id];
        $ttTime = $targetTimes[$this->ttAccount->id];
        $ytTime = $targetTimes[$this->ytAccount->id];

        $this->assertTrue($ttTime->greaterThan($igTime), 'TikTok should be staggered after Instagram');
        $this->assertTrue($ytTime->greaterThan($ttTime), 'YouTube should be staggered after TikTok');
    }

    public function test_smart_jitter_adds_natural_human_variance(): void
    {
        $service = app(AlgorithmicGrowthSchedulerService::class);
        $accounts = [$this->igAccount];
        $baseTime = now()->setTime(14, 0, 0);

        $targetTimes = $service->generateStaggeredTargetTimes(
            accounts: $accounts,
            baseTime: $baseTime,
            enableJitter: true,
            enableStagger: false
        );

        $igTime = $targetTimes[$this->igAccount->id];
        $diffMinutes = $baseTime->diffInMinutes($igTime);

        // Jitter should be between 3 and 9 minutes for Instagram
        $this->assertGreaterThanOrEqual(3, $diffMinutes);
        $this->assertLessThanOrEqual(10, $diffMinutes);
    }

    public function test_schedule_model_with_algorithmic_growth(): void
    {
        $schedule = Schedule::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'name' => 'Akıllı Keşfet Testi',
            'schedule_type' => 'algorithmic_growth',
            'growth_strategy' => 'balanced',
            'anti_spam_shield' => true,
            'enable_smart_jitter' => true,
            'enable_staggered_dispatch' => true,
            'is_active' => true,
        ]);

        $times = $schedule->getDailyRunTimes();
        $this->assertCount(3, $times, 'Balanced growth strategy should generate 3 peak waves');

        // Test max_capacity strategy (5 waves)
        $schedule->update(['growth_strategy' => 'max_capacity']);
        $reflector = new \ReflectionClass($schedule);
        $property = $reflector->getProperty('memoizedRunTimes');
        $property->setAccessible(true);
        $property->setValue($schedule, null);

        $timesMax = $schedule->getDailyRunTimes();
        $this->assertCount(5, $timesMax, 'Max capacity strategy should generate 5 peak waves to fully utilize all daily quotas');

        // Test conservative strategy
        $schedule->update(['growth_strategy' => 'conservative']);
        $property->setValue($schedule, null);

        $timesConservative = $schedule->getDailyRunTimes();
        $this->assertCount(2, $timesConservative, 'Conservative strategy should generate 2 peak waves');
    }

    public function test_scheduling_service_creates_targets_with_independent_times(): void
    {
        $schedulingService = app(SchedulingService::class);
        $schedule = Schedule::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'name' => 'Algoritmik Kural',
            'schedule_type' => 'algorithmic_growth',
            'growth_strategy' => 'balanced',
            'anti_spam_shield' => true,
            'enable_smart_jitter' => true,
            'enable_staggered_dispatch' => true,
            'is_active' => true,
        ]);

        $post = $schedulingService->createScheduledPost(
            video: $this->video,
            scheduledAt: now(),
            socialAccountIds: [$this->igAccount->id, $this->ttAccount->id],
            scheduleId: $schedule->id
        );

        $targets = $post->targets()->get();
        $this->assertCount(2, $targets);

        $target1 = $targets->firstWhere('social_account_id', $this->igAccount->id);
        $target2 = $targets->firstWhere('social_account_id', $this->ttAccount->id);

        $this->assertNotNull($target1->scheduled_at);
        $this->assertNotNull($target2->scheduled_at);
        $this->assertNotEquals($target1->scheduled_at->toDateTimeString(), $target2->scheduled_at->toDateTimeString(), 'Targets must not have identical timestamps due to anti-spam stagger');
    }

    public function test_filter_eligible_accounts_protects_daily_quotas(): void
    {
        $service = app(AlgorithmicGrowthSchedulerService::class);
        $accounts = collect([$this->igAccount, $this->ytAccount]);

        // Simüle et: YouTube hesabı bugün 5 gönderi kotasını doldurmuş olsun
        for ($i = 0; $i < 5; $i++) {
            $post = ScheduledPost::create([
                'workspace_id' => $this->workspace->id,
                'brand_id' => $this->brand->id,
                'video_id' => $this->video->id,
                'scheduled_at' => now()->subHours(12 - ($i * 2)),
                'status' => PublishStatus::PUBLISHED,
            ]);

            ScheduledPostTarget::create([
                'scheduled_post_id' => $post->id,
                'social_account_id' => $this->ytAccount->id,
                'platform_code' => 'youtube',
                'scheduled_at' => now()->subHours(12 - ($i * 2)),
                'correlation_id' => 'yt_quota_test_' . $i,
                'idempotency_key' => 'yt_quota_key_' . $i,
                'status' => PublishStatus::PUBLISHED,
            ]);
        }

        // Instagram ise henüz 0 gönderi yapmış olsun
        $eligible = $service->filterEligibleAccounts($accounts, now(), 'max_capacity');

        // YouTube kotasını doldurduğu için bu dalgada filtrelenmeli, sadece Instagram katılmalı!
        $this->assertCount(1, $eligible);
        $this->assertEquals($this->igAccount->id, $eligible->first()->id);
    }

    public function test_reschedule_pending_posts_to_algorithmic_waves(): void
    {
        $orchestrator = app(\App\Services\Automation\AutomationOrchestratorService::class);

        // 3 adet 5 dakikalık aralıklı eski tarz post oluşturalım
        for ($i = 0; $i < 3; $i++) {
            $post = ScheduledPost::create([
                'workspace_id' => $this->workspace->id,
                'brand_id' => $this->brand->id,
                'video_id' => $this->video->id,
                'scheduled_at' => now()->addMinutes(5 * ($i + 1)),
                'status' => PublishStatus::SCHEDULED,
            ]);

            ScheduledPostTarget::create([
                'scheduled_post_id' => $post->id,
                'social_account_id' => $this->igAccount->id,
                'platform_code' => 'instagram',
                'correlation_id' => 'resched_test_' . $i,
                'idempotency_key' => 'resched_key_' . $i,
                'status' => PublishStatus::SCHEDULED,
            ]);
        }

        $rescheduledCount = $orchestrator->reschedulePendingPostsToAlgorithmicWaves(now()->startOfDay());

        $this->assertEquals(3, $rescheduledCount);

        $posts = ScheduledPost::where('status', PublishStatus::SCHEDULED)->orderBy('scheduled_at')->get();

        // Gönderilerin her birinin farklı dalga saatlerine dağıldığını ve hedef scheduled_at'nin set edildiğini doğrula
        $this->assertCount(3, $posts);
        $hours = $posts->map(fn ($p) => (int) $p->scheduled_at->format('H'))->toArray();
        $this->assertNotEquals($hours[0], $hours[1], 'Posts should be distributed into different wave hours');
        
        $firstTarget = $posts->first()->targets->first();
        $this->assertNotNull($firstTarget->scheduled_at, 'Target scheduled_at should be populated with staggered time');
    }

    public function test_sequential_pool_progression_continues_from_last_video_without_looping_early(): void
    {
        $orchestrator = app(\App\Services\Automation\AutomationOrchestratorService::class);

        // Havuzda 1..25 arası videolar oluşturalım
        $videos = [];
        for ($i = 1; $i <= 25; $i++) {
            $v = Video::create([
                'workspace_id' => $this->workspace->id,
                'brand_id' => $this->brand->id,
                'title' => "{$i}. Video",
                'original_filename' => "video_{$i}.mp4",
                'storage_path' => "videos/video_{$i}.mp4",
                'file_size' => 1500000,
                'aspect_ratio' => '9:16',
                'status' => \App\Enums\VideoStatus::READY,
                'file_hash' => 'hash_test_' . $i,
                'duration_seconds' => 30,
            ]);
            $videos[$i] = $v;
        }

        // Simüle et: En son 20. Video paylaşılmış olsun
        ScheduledPost::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'video_id' => $videos[20]->id,
            'scheduled_at' => now()->subHour(),
            'status' => PublishStatus::PUBLISHED,
        ]);

        // Şimdi otomatik sıralı kural çalıştırılsın
        $schedule = Schedule::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'name' => 'Sıralı Test Kuralı',
            'schedule_type' => 'algorithmic_growth',
            'growth_strategy' => 'max_capacity',
            'time_of_day' => '12:00:00',
            'days_of_week' => [1, 2, 3, 4, 5, 6, 7],
            'target_social_account_ids' => [$this->igAccount->id],
            'is_active' => true,
        ]);

        $post = $orchestrator->runSchedule($schedule);

        // Kural: Son paylaşılan 20. video ise, YENİ PAYLAŞIM 21. VİDEODAN BAŞLAMALI!
        $this->assertNotNull($post);
        $this->assertEquals($videos[21]->id, $post->video_id, 'Next video after 20 must be 21!');
    }

    public function test_setup_default_platform_specific_rules_creates_all_seven(): void
    {
        $orchestrator = app(\App\Services\Automation\AutomationOrchestratorService::class);
        $created = $orchestrator->setupDefaultPlatformSpecificRules();

        $this->assertNotEmpty($created);
        $schedules = Schedule::where('schedule_type', 'algorithmic_growth')->get();
        $this->assertGreaterThanOrEqual(3, $schedules->count());
    }

    public function test_each_platform_schedule_has_distinct_peak_hours(): void
    {
        $igSchedule = new Schedule(['schedule_type' => 'algorithmic_growth', 'name' => 'Instagram Reels']);
        $ytSchedule = new Schedule(['schedule_type' => 'algorithmic_growth', 'name' => 'YouTube Shorts']);
        $fbSchedule = new Schedule(['schedule_type' => 'algorithmic_growth', 'name' => 'Facebook']);
        $xSchedule = new Schedule(['schedule_type' => 'algorithmic_growth', 'name' => 'X (Twitter)']);
        $liSchedule = new Schedule(['schedule_type' => 'algorithmic_growth', 'name' => 'LinkedIn']);

        $igTimes = $igSchedule->getDailyRunTimes();
        $ytTimes = $ytSchedule->getDailyRunTimes();
        $fbTimes = $fbSchedule->getDailyRunTimes();
        $xTimes = $xSchedule->getDailyRunTimes();
        $liTimes = $liSchedule->getDailyRunTimes();

        $this->assertCount(5, $igTimes);
        $this->assertCount(5, $ytTimes);
        $this->assertCount(5, $fbTimes);
        $this->assertCount(5, $xTimes);
        $this->assertCount(4, $liTimes, 'LinkedIn should have 4 peak wave slots');

        // Saatler birbirinin kopyası olmamalı, her platformun kendi saatleri olmalı!
        $this->assertNotEquals($igTimes, $ytTimes, 'Instagram and YouTube should have different peak times');
        $this->assertNotEquals($ytTimes, $fbTimes, 'YouTube and Facebook should have different peak times');
        $this->assertNotEquals($xTimes, $liTimes, 'X and LinkedIn should have different peak times');
    }

    public function test_custom_added_hours_are_sorted_and_identified(): void
    {
        $schedule = new Schedule([
            'schedule_type' => 'algorithmic_growth',
            'name' => 'Instagram Reels',
            'posting_times' => ['10:15', '13:00', '16:30', '19:45', '22:15', '18:30', '21:30', '02:00', '04:00'],
        ]);

        $times = $schedule->getDailyRunTimes();

        // 02:00 ve 04:00 en başta olmalı, saatler kesinlikle artan sırada sıralanmalı!
        $this->assertEquals(['02:00', '04:00', '10:15', '13:00', '16:30', '18:30', '19:45', '21:30', '22:15'], $times);

        $defaultSlots = $schedule->getDefaultPeakSlots();
        $this->assertContains('10:15', $defaultSlots);
        $this->assertContains('13:00', $defaultSlots);

        // Özel eklenen saatler (02:00, 04:00, 18:30, 21:30) defaultSlots içinde OLMAMALI (yeşil renklendirilecek)
        $this->assertNotContains('02:00', $defaultSlots);
        $this->assertNotContains('04:00', $defaultSlots);
        $this->assertNotContains('18:30', $defaultSlots);
        $this->assertNotContains('21:30', $defaultSlots);
    }
}
