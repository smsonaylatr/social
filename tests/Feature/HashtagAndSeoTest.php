<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Hashtag;
use App\Models\KeywordLibrary;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\SEO\HashtagOptimizationService;
use Database\Seeders\InitialSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HashtagAndSeoTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected Brand $brand;
    protected User $admin;
    protected Video $video;
    protected HashtagOptimizationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSystemSeeder::class);

        $this->workspace = Workspace::where('slug', 'default-workspace')->first();
        $this->brand = Brand::where('slug', 'patenli-ayakkabilar')->first();
        $this->admin = User::where('email', 'admin@socialflow.ai')->first();
        $this->service = app(HashtagOptimizationService::class);

        $this->video = Video::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'title' => 'Test SEO Video',
            'original_filename' => 'seo.mp4',
            'storage_path' => 'videos/seo.mp4',
            'file_hash' => hash('sha256', 'seo'),
            'file_size' => 1000000,
            'status' => 'ready',
        ]);
    }

    public function test_hashtag_platform_limit_and_blacklist_enforcement(): void
    {
        // Add a blacklisted hashtag
        Hashtag::create([
            'workspace_id' => $this->workspace->id,
            'tag' => 'yasaklihashtag',
            'category' => 'general',
            'is_blacklisted' => true,
        ]);

        // Her platform için 5-10 arasında hashtag (standart 10: 5 anahtar kelime + 5 keşfet)
        $tagsForX = $this->service->getOptimizedHashtags($this->video, 'x', 10);
        $this->assertGreaterThanOrEqual(5, count($tagsForX));
        $this->assertLessThanOrEqual(10, count($tagsForX));
        $this->assertNotContains('yasaklihashtag', $tagsForX);

        // For Instagram: 8 istendiğinde 8 döner
        $tagsForIg = $this->service->getOptimizedHashtags($this->video, 'instagram', 8);
        $this->assertCount(8, $tagsForIg);
        $this->assertNotContains('yasaklihashtag', $tagsForIg);
    }

    public function test_record_hashtag_usage(): void
    {
        $tag = Hashtag::where('tag', 'patenliayakkabi')->first();
        $initialUsage = $tag->usage_count;

        $this->service->recordHashtagUsage(['#patenliayakkabi'], $this->workspace->id);

        $this->assertEquals($initialUsage + 1, $tag->fresh()->usage_count);
        $this->assertNotNull($tag->fresh()->last_used_at);
    }

    public function test_admin_can_access_hashtag_and_keyword_resources(): void
    {
        $responseHashtags = $this->actingAs($this->admin)->get('/admin/hashtags');
        $responseHashtags->assertStatus(200);
        $responseHashtags->assertSee('Hashtag Havuzu');

        $responseKeywords = $this->actingAs($this->admin)->get('/admin/keyword-libraries');
        $responseKeywords->assertStatus(200);
        $responseKeywords->assertSee('SEO ve Anahtar Kelimeler');
    }

    public function test_relevant_keywords_fetched_from_library_and_rotated(): void
    {
        $keywords = $this->service->getRelevantKeywords($this->video, 'instagram', 3);
        $this->assertCount(3, $keywords);
        $this->assertNotEmpty($keywords[0]);

        $firstKw = KeywordLibrary::where('keyword', $keywords[0])->first();
        $this->assertNotNull($firstKw);
        $this->assertEquals(0, $firstKw->usage_count);

        // Mark usage
        $this->service->recordKeywordUsage([$keywords[0]], $this->workspace->id);
        $this->assertEquals(1, $firstKw->fresh()->usage_count);
        $this->assertNotNull($firstKw->fresh()->last_used_at);

        // Content detection
        $this->service->recordKeywordUsageFromContent("Bu bir {$keywords[1]} içeren gönderidir.", $this->workspace->id);
        $secondKw = KeywordLibrary::where('keyword', $keywords[1])->first();
        $this->assertEquals(1, $secondKw->fresh()->usage_count);
        $this->assertNotNull($secondKw->fresh()->last_used_at);
    }

    public function test_scheduled_post_includes_seo_keywords_from_memory(): void
    {
        $platform = \App\Models\SocialPlatform::where('code', 'instagram')->first();
        $socialAccount = \App\Models\SocialAccount::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'platform_id' => $platform->id,
            'account_id' => 'ig_test_123',
            'name' => 'Instagram Test',
            'username' => 'testig',
            'status' => 'active',
        ]);

        $schedulingService = app(\App\Services\Publisher\SchedulingService::class);

        $scheduledPost = $schedulingService->createScheduledPost(
            video: $this->video,
            scheduledAt: now(),
            socialAccountIds: [$socialAccount->id]
        );

        $target = $scheduledPost->targets->first();
        $this->assertNotNull($target);

        // Check caption includes at least one keyword from KeywordLibrary
        $allKeywords = KeywordLibrary::pluck('keyword')->toArray();
        $found = false;
        foreach ($allKeywords as $kw) {
            if (mb_stripos($target->caption, $kw) !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Post caption must include keywords from the SEO Keyword Library memory.');
    }

    public function test_platform_hashtags_between_5_and_10_with_keywords_and_explore(): void
    {
        $platforms = ['instagram', 'tiktok', 'youtube', 'facebook', 'x', 'pinterest'];

        foreach ($platforms as $platform) {
            $tags = $this->service->getOptimizedHashtags($this->video, $platform, null, false);
            $count = count($tags);

            $this->assertGreaterThanOrEqual(5, $count, "{$platform} must have at least 5 hashtags");
            $this->assertLessThanOrEqual(10, $count, "{$platform} must not exceed 10 hashtags");
            $this->assertEquals(10, $count, "{$platform} should default to 10 hashtags (5 keyword + 5 explore)");
        }
    }
}
