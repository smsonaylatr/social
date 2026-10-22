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

        // For X (Twitter): maximum 2 hashtags
        $tagsForX = $this->service->getOptimizedHashtags($this->video, 'x', 10);
        $this->assertLessThanOrEqual(2, count($tagsForX));
        $this->assertNotContains('yasaklihashtag', $tagsForX);

        // For Instagram: can take up to 8
        $tagsForIg = $this->service->getOptimizedHashtags($this->video, 'instagram', 8);
        $this->assertNotEmpty($tagsForIg);
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
}
