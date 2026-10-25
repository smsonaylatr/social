<?php

namespace Tests\Feature;

use App\Filament\Widgets\PlatformStatusOverviewWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\SystemHealthOverviewWidget;
use App\Models\Brand;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\InitialSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSystemSeeder::class);
    }

    public function test_admin_login_page_is_accessible(): void
    {
        $response = $this->get('/admin/login');
        $response->assertStatus(200);
    }

    public function test_admin_can_authenticate_with_credentials(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Auth::attempt([
            'email' => 'admin@socialflow.ai',
            'password' => 'Admin123!*',
        ]));
    }

    public function test_default_workspace_and_brand_are_seeded(): void
    {
        $workspace = Workspace::where('slug', 'default-workspace')->first();
        $this->assertNotNull($workspace);

        $brand = Brand::where('slug', 'patenli-ayakkabilar')->first();
        $this->assertNotNull($brand);
        $this->assertEquals('Walk. Roll. Fly.', $brand->slogan);
        $this->assertEquals('https://patenliayakkabilar.com', $brand->website);
    }

    public function test_seven_social_platforms_are_registered(): void
    {
        $count = SocialPlatform::whereIn('code', [
            'facebook', 'instagram', 'youtube', 'x', 'pinterest', 'tiktok', 'linkedin'
        ])->count();

        $this->assertEquals(7, $count);
    }

    public function test_authenticated_admin_can_access_dashboard(): void
    {
        $admin = User::where('email', 'admin@socialflow.ai')->first();
        $this->assertNotNull($admin);

        $response = $this->actingAs($admin)->get('/admin');
        $response->assertStatus(200);
        $response->assertSee('SocialFlow AI');
    }

    public function test_dashboard_widgets_render(): void
    {
        $admin = User::where('email', 'admin@socialflow.ai')->first();
        $this->actingAs($admin);

        Livewire::test(StatsOverviewWidget::class)
            ->assertSee('Video Havuzu')
            ->assertSee('Bugünkü Yayınlar');

        Livewire::test(PlatformStatusOverviewWidget::class)
            ->assertSee('Sosyal Medya Platformları ve API Sağlık Durumu')
            ->assertSee('Facebook')
            ->assertSee('Instagram')
            ->assertSee('YouTube')
            ->assertSee('TikTok');

        Livewire::test(SystemHealthOverviewWidget::class)
            ->assertSee('Sistem Sağlık ve Altyapı Durumu')
            ->assertSee('Veritabanı (MySQL)');
    }

    public function test_audit_logs_are_recorded_and_accessible_in_admin(): void
    {
        $admin = User::where('email', 'admin@socialflow.ai')->first();
        $this->actingAs($admin);

        // Verify initial audit log created by seeder
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'system_init',
        ]);

        // Access Audit Logs page in Filament
        $response = $this->get('/admin/audit-logs');
        $response->assertStatus(200);
        $response->assertSee('İşlem ve Denetim Kayıtları');
    }
}
