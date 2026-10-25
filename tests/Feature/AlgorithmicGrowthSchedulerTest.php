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

        // Test conservative strategy
        $schedule->update(['growth_strategy' => 'conservative']);
        // Reset memoized
        $reflector = new \ReflectionClass($schedule);
        $property = $reflector->getProperty('memoizedRunTimes');
        $property->setAccessible(true);
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
}
