<?php

namespace Tests\Feature;

use App\Filament\Pages\SocialChannelsPage;
use App\Models\Brand;
use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\InitialSystemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SocialChannelsPageTest extends TestCase
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

    public function test_admin_can_access_social_channels_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/social-channels');
        $response->assertStatus(200);
        $response->assertSee('TikTok');
        $response->assertSee('Instagram');
        $response->assertSee('Channels (Kanallar)');
    }

    public function test_can_switch_active_channel_platform(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SocialChannelsPage::class)
            ->assertSet('activePlatform', 'tiktok')
            ->call('selectPlatform', 'instagram')
            ->assertSet('activePlatform', 'instagram')
            ->call('selectPlatform', 'youtube')
            ->assertSet('activePlatform', 'youtube');
    }

    public function test_direct_connect_authorizes_channel_and_grants_permissions(): void
    {
        $test = Livewire::actingAs($this->admin)
            ->test(SocialChannelsPage::class)
            ->call('selectPlatform', 'tiktok')
            ->call('openConnectModal', 'tiktok')
            ->assertSet('showConnectModal', true)
            ->assertSet('connectPlatformCode', 'tiktok');

        $this->assertNotEmpty($test->get('selectedScopes'));

        $test->set('connectName', 'Patenli Ayakkabılar TikTok')
            ->set('connectUsername', 'patenliayakkabilar')
            ->call('connectAccount')
            ->assertSet('showConnectModal', false)
            ->assertSet('showBanner', true)
            ->assertHasNoErrors();

        // Verify account exists in database without brand coupling (SaaS multi-tenant ready)
        $account = SocialAccount::where('name', 'Patenli Ayakkabılar TikTok')->first();
        $this->assertNotNull($account);
        $this->assertNull($account->brand_id);
        $this->assertEquals('active', $account->status);
        $this->assertEquals('patenliayakkabilar', $account->username);

        // Verify credentials and permissions created
        $this->assertNotNull($account->credential);
        $this->assertEquals('partner_oauth2', $account->credential->extra_data['auth_mode'] ?? null);
        $this->assertGreaterThan(0, $account->permissions()->count());
        $this->assertTrue($account->permissions()->where('permission_name', 'video.publish')->exists());
    }

    public function test_can_save_partner_oauth_app_config(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SocialChannelsPage::class)
            ->call('selectPlatform', 'tiktok')
            ->set('partnerClientId', 'tt_partner_client_123')
            ->set('partnerClientSecret', 'tt_partner_secret_456')
            ->set('partnerEnvironment', 'production')
            ->call('savePartnerConfig')
            ->assertSet('showBanner', true);

        $this->assertEquals(
            'tt_partner_client_123',
            \App\Models\Setting::get('partner_tiktok_client_id')
        );
    }

    public function test_official_partner_oauth_redirect_and_callback_flow(): void
    {
        // 1. In sandbox mode (no external client_id yet), /oauth/redirect/tiktok redirects to /oauth/callback/tiktok
        $redirectResponse = $this->actingAs($this->admin)->get('/oauth/redirect/tiktok');
        $redirectResponse->assertRedirect();
        $targetUrl = $redirectResponse->headers->get('Location');
        $this->assertStringContainsString('/oauth/callback/tiktok?code=partner_sandbox_auth_code_', $targetUrl);

        // 2. Follow the callback URL and verify the account is created without a brand
        $callbackResponse = $this->actingAs($this->admin)->get($targetUrl);
        $callbackResponse->assertRedirect('/admin/social-channels?platform=tiktok&success=1');

        $account = SocialAccount::where('account_id', 'like', 'tiktok_partner_%')->first();
        $this->assertNotNull($account);
        $this->assertNull($account->brand_id);
        $this->assertEquals('active', $account->status);
        $this->assertNotNull($account->credential);
    }

    public function test_can_connect_all_nine_platform_apis_at_once(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SocialChannelsPage::class)
            ->call('connectAllPlatforms')
            ->assertSet('showBanner', true);

        $this->assertEquals(9, SocialAccount::where('status', 'active')->count());

        $manager = app(\App\Services\Social\SocialPublisherManager::class);
        foreach (['tiktok', 'instagram', 'youtube', 'facebook', 'x', 'pinterest', 'linkedin', 'whatsapp', 'telegram'] as $code) {
            $publisher = $manager->getPublisher($code);
            $account = SocialAccount::whereHas('platform', fn ($q) => $q->where('code', $code))->first();
            $this->assertNotNull($account, "Account for {$code} should be connected");
            $this->assertTrue($publisher->validateConnection($account));
            $this->assertTrue($publisher->refreshToken($account));
        }
    }

    public function test_social_webhooks_verify_challenges(): void
    {
        // Meta challenge verification
        $metaResp = $this->get('/webhooks/social/instagram?hub_challenge=challenge_98765');
        $metaResp->assertStatus(200);
        $this->assertEquals('challenge_98765', $metaResp->getContent());

        // X CRC challenge verification
        $xResp = $this->get('/webhooks/social/x?crc_token=crc_test_123');
        $xResp->assertStatus(200);
        $xResp->assertJsonStructure(['response_token']);
    }

    public function test_refreshing_permissions_triggers_manychat_style_banner(): void
    {
        // First connect account
        $platform = SocialPlatform::where('code', 'instagram')->first();
        $account = SocialAccount::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'platform_id' => $platform->id,
            'account_id' => 'ig_test_999',
            'name' => 'Patenli Instagram Test',
            'username' => 'patenliayakkabilar',
            'status' => 'active',
        ]);

        $account->credential()->create([
            'access_token' => 'mock_token_instagram',
            'token_type' => 'Bearer',
        ]);

        Livewire::actingAs($this->admin)
            ->test(SocialChannelsPage::class)
            ->call('selectPlatform', 'instagram')
            ->call('refreshPermissions', $account->id)
            ->assertSet('showBanner', true)
            ->assertSee('Instagram channel permissions successfully refreshed');

        $account->refresh();
        $this->assertNotNull($account->last_synced_at);
        $this->assertEquals('active', $account->status);
    }

    public function test_can_disconnect_channel_account(): void
    {
        $platform = SocialPlatform::where('code', 'tiktok')->first();
        $account = SocialAccount::create([
            'workspace_id' => $this->workspace->id,
            'brand_id' => $this->brand->id,
            'platform_id' => $platform->id,
            'account_id' => 'tt_test_888',
            'name' => 'TikTok to Disconnect',
            'username' => 'patenli_tt',
            'status' => 'active',
        ]);

        Livewire::actingAs($this->admin)
            ->test(SocialChannelsPage::class)
            ->call('confirmDisconnect', $account->id)
            ->assertSet('showDisconnectModal', true)
            ->assertSet('accountToDisconnectId', $account->id)
            ->call('disconnectAccount')
            ->assertSet('showDisconnectModal', false);

        $this->assertNull(SocialAccount::find($account->id));
    }
}
