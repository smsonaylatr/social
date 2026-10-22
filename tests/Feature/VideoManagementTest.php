<?php

namespace Tests\Feature;

use App\Enums\VideoStatus;
use App\Models\Brand;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Models\Workspace;
use App\Services\Media\VideoProcessingService;
use Database\Seeders\InitialSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspace;
    protected Brand $brand;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSystemSeeder::class);

        $this->workspace = Workspace::where('slug', 'default-workspace')->first();
        $this->brand = Brand::where('slug', 'patenli-ayakkabilar')->first();
        $this->admin = User::where('email', 'admin@socialflow.ai')->first();
    }

    public function test_can_create_video_record_with_technical_metadata(): void
    {
        $category = VideoCategory::first();

        $video = Video::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'category_id' => $category->id,
            'title' => 'Patenli Ayakkabı Hızlı Dönüşler',
            'original_filename' => 'sample_reels.mp4',
            'storage_path' => 'videos/sample_reels.mp4',
            'file_hash' => hash('sha256', 'mock_video_bytes'),
            'file_size' => 10485760, // 10 MB
            'duration_seconds' => 45,
            'width' => 1080,
            'height' => 1920,
            'aspect_ratio' => '9:16',
            'fps' => 30.00,
            'mime_type' => 'video/mp4',
            'status' => VideoStatus::READY,
        ]);

        $this->assertDatabaseHas('videos', [
            'id' => $video->id,
            'title' => 'Patenli Ayakkabı Hızlı Dönüşler',
            'aspect_ratio' => '9:16',
            'status' => VideoStatus::READY->value,
        ]);

        $this->assertEquals('00:45', $video->formatted_duration);
        $this->assertEquals('10 MB', $video->formatted_file_size);
    }

    public function test_duplicate_detection_by_hash(): void
    {
        $hash = hash('sha256', 'unique_video_content');

        Video::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'title' => 'İlk Video',
            'original_filename' => 'video1.mp4',
            'storage_path' => 'videos/video1.mp4',
            'file_hash' => $hash,
            'file_size' => 5000000,
            'status' => VideoStatus::READY,
        ]);

        $service = app(VideoProcessingService::class);
        $duplicate = $service->findDuplicate($hash);

        $this->assertNotNull($duplicate);
        $this->assertEquals('İlk Video', $duplicate->title);
    }

    public function test_platform_compatibility_check(): void
    {
        $service = app(VideoProcessingService::class);

        $validReelsVideo = Video::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'title' => 'Uygun Reels',
            'original_filename' => 'reels.mp4',
            'storage_path' => 'videos/reels.mp4',
            'file_hash' => hash('sha256', 'v1'),
            'file_size' => 5000000,
            'duration_seconds' => 30,
            'width' => 1080,
            'height' => 1920,
            'aspect_ratio' => '9:16',
            'status' => VideoStatus::READY,
        ]);

        $resultIg = $service->validatePlatformCompatibility($validReelsVideo, 'instagram');
        $this->assertTrue($resultIg['compatible']);
        $this->assertEmpty($resultIg['warnings']);

        // Long video for Instagram Reels warning
        $longVideo = Video::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'title' => 'Uzun Video',
            'original_filename' => 'long.mp4',
            'storage_path' => 'videos/long.mp4',
            'file_hash' => hash('sha256', 'v2'),
            'file_size' => 5000000,
            'duration_seconds' => 120, // > 90s
            'width' => 1920,
            'height' => 1080,
            'aspect_ratio' => '16:9',
            'status' => VideoStatus::READY,
        ]);

        $resultIgLong = $service->validatePlatformCompatibility($longVideo, 'instagram');
        $this->assertNotEmpty($resultIgLong['warnings']);

        // Restricted platform check
        $restrictedVideo = Video::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'title' => 'TikTok Engelli Video',
            'original_filename' => 'restricted.mp4',
            'storage_path' => 'videos/restricted.mp4',
            'file_hash' => hash('sha256', 'v3'),
            'file_size' => 5000000,
            'status' => VideoStatus::READY,
            'restricted_platforms' => ['tiktok', 'youtube'],
        ]);

        $resultTiktok = $service->validatePlatformCompatibility($restrictedVideo, 'tiktok');
        $this->assertFalse($resultTiktok['compatible']);
        $this->assertNotEmpty($resultTiktok['errors']);
    }

    public function test_admin_can_access_video_resource_list_and_view(): void
    {
        $video = Video::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'title' => 'Test Video Listeleme',
            'original_filename' => 'test.mp4',
            'storage_path' => 'videos/test.mp4',
            'file_hash' => hash('sha256', 'test_video'),
            'file_size' => 2048000,
            'duration_seconds' => 20,
            'aspect_ratio' => '9:16',
            'status' => VideoStatus::READY,
        ]);

        $responseList = $this->actingAs($this->admin)->get('/admin/videos');
        $responseList->assertStatus(200);
        $responseList->assertSee('Test Video Listeleme');

        $responseView = $this->actingAs($this->admin)->get("/admin/videos/{$video->id}");
        $responseView->assertStatus(200);
        $responseView->assertSee('Test Video Listeleme');
        $responseView->assertSee('Platform Uyumluluk Analizi');
    }
}
