<?php

namespace Tests\Feature;

use App\Filament\Pages\AiStudio;
use App\Models\AiContentGeneration;
use App\Models\Brand;
use App\Models\User;
use App\Models\Video;
use App\Models\Workspace;
use App\Services\AI\Contracts\AiContentProviderInterface;
use Database\Seeders\InitialSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiContentStudioTest extends TestCase
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
            'title' => 'Işıklı Patenli Spor Ayakkabı Tanıtımı',
            'original_filename' => 'isikli_paten.mp4',
            'storage_path' => 'videos/isikli_paten.mp4',
            'file_hash' => hash('sha256', 'isikli_paten'),
            'file_size' => 2000000,
            'duration_seconds' => 40,
            'aspect_ratio' => '9:16',
            'status' => 'ready',
        ]);
    }

    public function test_ai_service_generates_all_seven_platforms_content(): void
    {
        /** @var AiContentProviderInterface $service */
        $service = app(AiContentProviderInterface::class);

        $result = $service->generateForVideo($this->video, $this->brand);

        $this->assertTrue($result->isTestMode);
        $this->assertCount(7, $result->platforms);

        // Check platforms
        $platforms = ['instagram', 'facebook', 'youtube', 'x', 'pinterest', 'tiktok', 'linkedin'];
        foreach ($platforms as $platform) {
            $pDto = $result->getPlatform($platform);
            $this->assertNotNull($pDto, "Platform {$platform} should be present in results");
            $this->assertNotEmpty($pDto->caption);
            $this->assertNotEmpty($pDto->callToAction);
        }

        // Check X (Twitter) character limit (<= 280)
        $xContent = $result->getPlatform('x');
        $this->assertLessThanOrEqual(280, mb_strlen($xContent->caption));

        // Check database records persisted
        $dbCount = AiContentGeneration::where('video_id', $this->video->id)->count();
        $this->assertEquals(7, $dbCount);
    }

    public function test_ai_studio_livewire_page_interaction(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(AiStudio::class)
            ->set('selectedVideoId', $this->video->id)
            ->call('generateAiContent')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ai_content_generations', [
            'video_id' => $this->video->id,
            'platform_code' => 'instagram',
        ]);

        $response = $this->get('/admin/ai-studio');
        $response->assertStatus(200);
        $response->assertSee('AI İçerik ve Sosyal SEO Stüdyosu');
        $response->assertSee('Platform İçin Akıllı İçerik Üret');
        $response->assertSee('Yalnızca Bağlı Hesapları Göster');
    }

    public function test_ai_studio_filters_unconnected_platforms(): void
    {
        $this->actingAs($this->admin);

        // Connect only Instagram
        $platform = \App\Models\SocialPlatform::where('code', 'instagram')->first();
        \App\Models\SocialAccount::create([
            'workspace_id' => $this->workspace->id,
            'platform_id' => $platform->id,
            'account_id' => 'ig_123',
            'name' => 'Instagram Test',
            'status' => 'active',
        ]);

        $component = Livewire::test(AiStudio::class)
            ->set('selectedVideoId', $this->video->id);

        $available = $component->get('availablePlatforms');
        $this->assertArrayHasKey('instagram', $available);
        $this->assertArrayNotHasKey('tiktok', $available, 'TikTok should not be in available platforms when inactive and onlyConnected is true');
    }
}
