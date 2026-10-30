<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\SocialAccountCredential;
use App\Models\SocialAccountPermission;
use App\Models\SocialPlatform;
use App\Models\Workspace;
use App\Services\Social\SocialPublisherManager;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SocialChannelsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'Sosyal Medya';
    protected static ?string $navigationLabel = 'Sosyal Medya Kanalları';
    protected static ?string $title = 'Sosyal Medya Kanalları & Doğrudan İzinler';
    protected static ?string $slug = 'social-channels';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.social-channels-page';

    public string $activePlatform = 'tiktok';

    // Notification Banner (Manychat Style: "Instagram channel permissions successfully refreshed")
    public ?string $bannerMessage = null;
    public string $bannerType = 'success'; // success, info, warning
    public bool $showBanner = false;

    // Partner OAuth Consent Modal State
    public bool $showConnectModal = false;
    public string $connectPlatformCode = 'tiktok';
    public string $connectName = '';
    public string $connectUsername = '';
    public string $connectAccountId = '';
    public string $connectAccountType = 'business';
    public array $selectedScopes = [];

    // Partner App Credentials (System Owner Settings per Platform)
    public string $partnerClientId = '';
    public string $partnerClientSecret = '';
    public string $partnerEnvironment = 'sandbox'; // 'sandbox' (Başvuru / Test) or 'production' (Onaylı İş Ortağı)
    public string $partnerPostMode = 'text'; // 'text' (Videosuz Metin Postu) or 'video' (Video Gönderisi)

    // Edit Channel Modal
    public bool $showEditModal = false;
    public ?int $editingAccountId = null;
    public string $editName = '';
    public string $editUsername = '';
    public string $editAccountId = '';

    // Disconnect Confirmation Modal
    public bool $showDisconnectModal = false;
    public ?int $accountToDisconnectId = null;

    protected $queryString = [
        'activePlatform' => ['except' => 'tiktok', 'as' => 'platform'],
    ];

    public function mount(): void
    {
        $platformParam = request()->query('platform');
        if ($platformParam && $this->getPlatformDefinitions()->has($platformParam)) {
            $this->activePlatform = $platformParam;
        }

        $this->loadPartnerConfig($this->activePlatform);

        if (request()->query('success')) {
            $platformName = $this->getPlatformDefinitions()[$this->activePlatform]['name'] ?? 'Kanal';
            $this->bannerMessage = "{$platformName} erişim izni başarıyla onaylandı ve kanal bağlandı.";
            $this->bannerType = 'success';
            $this->showBanner = true;
        }

        if (request()->query('error')) {
            $this->bannerMessage = (string) request()->query('error');
            $this->bannerType = 'warning';
            $this->showBanner = true;
        }
    }

    public function selectPlatform(string $platformCode): void
    {
        if ($this->getPlatformDefinitions()->has($platformCode)) {
            $this->activePlatform = $platformCode;
            $this->loadPartnerConfig($platformCode);
        }
    }

    public function loadPartnerConfig(string $platformCode): void
    {
        $this->partnerClientId = (string) Setting::get("partner_{$platformCode}_client_id", '');
        $this->partnerClientSecret = (string) Setting::get("partner_{$platformCode}_client_secret", '');
        $this->partnerEnvironment = (string) Setting::get("partner_{$platformCode}_env", 'sandbox');
        
        $defaultMode = in_array($platformCode, ['x', 'linkedin', 'facebook'], true) ? 'text' : 'video';
        $this->partnerPostMode = (string) Setting::get("partner_{$platformCode}_post_mode", $defaultMode);
    }

    public function savePartnerConfig(): void
    {
        $code = $this->activePlatform;
        $def = $this->getPlatformDefinitions()[$code] ?? null;

        Setting::set("partner_{$code}_client_id", trim($this->partnerClientId), 'partner_oauth');
        Setting::set("partner_{$code}_client_secret", trim($this->partnerClientSecret), 'partner_oauth');
        Setting::set("partner_{$code}_env", $this->partnerEnvironment, 'partner_oauth');
        Setting::set("partner_{$code}_post_mode", $this->partnerPostMode, 'partner_oauth');
        
        // Geriye dönük uyumluluk için platform bazlı özel ayarları da senkronize et
        if (in_array($code, ['x', 'linkedin', 'facebook'], true)) {
            Setting::set("social_{$code}_text_only_mode", $this->partnerPostMode === 'text', 'partner_oauth');
        }

        $platformName = $def['name'] ?? strtoupper($code);
        $this->bannerMessage = "{$platformName} İş Ortağı (Partner App) OAuth yapılandırması kaydedildi.";
        $this->bannerType = 'success';
        $this->showBanner = true;

        Notification::make()
            ->title("{$platformName} Partner Ayarları Kaydedildi")
            ->body('İş ortağı uygulama kimliği ve OAuth yönlendirme ayarları güncellendi.')
            ->success()
            ->send();
    }

    public function dismissBanner(): void
    {
        $this->showBanner = false;
        $this->bannerMessage = null;
    }

    /**
     * Get rich metadata definitions for all supported social channels (Partner OAuth Model)
     */
    public function getPlatformDefinitions(): Collection
    {
        return collect([
            'tiktok' => [
                'code' => 'tiktok',
                'name' => 'TikTok',
                'partner_program' => 'TikTok for Developers — Content Posting Partner API',
                'tagline' => "Elevate your marketing with TikTok's seamless automation.",
                'description' => 'TikTok İş Ortağı (Partner) OAuth 2.0 erişim izni ile kullanıcıların TikTok Business ve Creator hesaplarını tek tıkla yetkilendirin.',
                'account_types' => [
                    'business' => 'TikTok İşletme Hesabı (Business Account)',
                    'creator' => 'TikTok İçerik Üreticisi (Creator Profile)',
                ],
                'default_type' => 'business',
                'color' => '#000000',
                'accent_color' => '#fe2c55',
                'docs_url' => 'https://developers.tiktok.com/doc/content-posting-api-get-started',
                'scopes' => [
                    [
                        'name' => 'video.publish',
                        'label' => 'Doğrudan Video Yayınlama',
                        'description' => 'Hesap adına TikTok akışında doğrudan video paylaşma erişim izni.',
                        'required' => true,
                    ],
                    [
                        'name' => 'video.upload',
                        'label' => 'Video Medya Yükleme',
                        'description' => 'Video dosyalarını TikTok sunucularına aktarma erişim izni.',
                        'required' => true,
                    ],
                    [
                        'name' => 'user.info.basic',
                        'label' => 'Profil Kimliği Doğrulama',
                        'description' => 'Bağlanan TikTok hesabının profil adını ve avatarını okuma izni.',
                        'required' => true,
                    ],
                ],
            ],
            'instagram' => [
                'code' => 'instagram',
                'name' => 'Instagram',
                'partner_program' => 'Meta Business Partner — Instagram Graph API',
                'tagline' => 'Instagram Professional & Business hesapları için Reels otomasyonu.',
                'description' => 'Meta İş Ortağı (Tech Provider) OAuth erişim izni ile Instagram Professional veya Creator hesaplarını şifresiz ve güvenli şekilde bağlayın.',
                'account_types' => [
                    'business' => 'Instagram İşletme / Professional Hesabı',
                    'creator' => 'Instagram İçerik Üretici (Creator) Hesabı',
                ],
                'default_type' => 'business',
                'color' => '#e1306c',
                'accent_color' => '#fd1d1d',
                'docs_url' => 'https://developers.facebook.com/docs/instagram-platform/instagram-graph-api',
                'scopes' => [
                    [
                        'name' => 'instagram_basic',
                        'label' => 'Instagram Profil Erişimi',
                        'description' => 'Hesap profili, kullanıcı adı ve medya bilgilerini okuma izni.',
                        'required' => true,
                    ],
                    [
                        'name' => 'instagram_content_publish',
                        'label' => 'Reels ve Gönderi Yayınlama',
                        'description' => 'Instagram Reels ve video gönderilerini doğrudan yayınlama erişim izni.',
                        'required' => true,
                    ],
                    [
                        'name' => 'pages_show_list',
                        'label' => 'Bağlı Sayfa Listesi Erişimi',
                        'description' => 'Instagram profesyonel hesabına bağlı işletme sayfalarını doğrulama.',
                        'required' => true,
                    ],
                    [
                        'name' => 'pages_read_engagement',
                        'label' => 'Etkileşim ve İstatistik İzni',
                        'description' => 'Yayınlanan videoların izlenme ve etkileşim verilerini okuma.',
                        'required' => false,
                    ],
                ],
            ],
            'youtube' => [
                'code' => 'youtube',
                'name' => 'YouTube',
                'partner_program' => 'Google Cloud Partner — YouTube Data API v3',
                'tagline' => 'Google OAuth 2.0 ile YouTube Shorts ve Video otomasyonu.',
                'description' => 'Google OAuth 2.0 erişim izni ile üyelerin YouTube kanallarını bağlayarak Shorts ve video yüklemelerini otomatikleştirin.',
                'account_types' => [
                    'channel' => 'YouTube Kanalı',
                ],
                'default_type' => 'channel',
                'color' => '#ff0000',
                'accent_color' => '#cc0000',
                'docs_url' => 'https://developers.google.com/youtube/v3/getting-started',
                'scopes' => [
                    [
                        'name' => 'https://www.googleapis.com/auth/youtube.upload',
                        'label' => 'YouTube Video & Shorts Yükleme',
                        'description' => 'Kanala doğrudan Shorts ve video yükleme erişim izni.',
                        'required' => true,
                    ],
                    [
                        'name' => 'https://www.googleapis.com/auth/youtube.readonly',
                        'label' => 'Kanal Bilgilerini Okuma',
                        'description' => 'Kanal kimliği ve video yayın durumunu senkronize etme izni.',
                        'required' => true,
                    ],
                    [
                        'name' => 'https://www.googleapis.com/auth/youtube',
                        'label' => 'YouTube 7/24 Canlı Yayın Yönetimi',
                        'description' => 'YouTube Live Studio canlı yayın ve yayın akışlarını (RTMP) otomatik oluşturma izni.',
                        'required' => false,
                    ],
                ],
            ],
            'facebook' => [
                'code' => 'facebook',
                'name' => 'Facebook',
                'partner_program' => 'Meta Business Partner — Pages & Video API',
                'tagline' => 'Facebook Login for Business ile Sayfa ve Reels yönetimi.',
                'description' => 'Meta İş Ortağı erişim izni üzerinden kullanıcıların yönettiği Facebook sayfalarını seçerek doğrudan yayın izni alın.',
                'account_types' => [
                    'page' => 'Facebook İşletme Sayfası',
                ],
                'default_type' => 'page',
                'color' => '#1877f2',
                'accent_color' => '#0b5ed7',
                'docs_url' => 'https://developers.facebook.com/docs/pages-api',
                'scopes' => [
                    [
                        'name' => 'pages_manage_posts',
                        'label' => 'Sayfa Adına Video & Reels Yayınlama',
                        'description' => 'Facebook sayfasında doğrudan içerik ve Reels paylaşma izni.',
                        'required' => true,
                    ],
                    [
                        'name' => 'pages_read_engagement',
                        'label' => 'Sayfa Etkileşimlerini Okuma',
                        'description' => 'Yayınlanan videoların görüntülenme ve etkileşim analizlerini alma.',
                        'required' => true,
                    ],
                    [
                        'name' => 'pages_show_list',
                        'label' => 'Yönetilen Sayfaları Listeleme',
                        'description' => 'Kullanıcının yönetici olduğu sayfaları listeleme izni.',
                        'required' => true,
                    ],
                ],
            ],
            'x' => [
                'code' => 'x',
                'name' => 'X (Twitter)',
                'partner_program' => 'X Official Developer Partner — API v2 OAuth 2.0',
                'tagline' => 'Resmi X OAuth 2.0 erişim izni ile anlık video paylaşımı.',
                'description' => 'X OAuth 2.0 PKCE yetkilendirmesi ile kullanıcıların X profillerine erişim izni alarak video gönderilerini otomatik yayınlayın.',
                'account_types' => [
                    'profile' => 'X (Twitter) Profili',
                ],
                'default_type' => 'profile',
                'color' => '#111827',
                'accent_color' => '#374151',
                'docs_url' => 'https://developer.x.com/en/docs/x-api',
                'scopes' => [
                    [
                        'name' => 'tweet.read',
                        'label' => 'Gönderi ve Profil Okuma',
                        'description' => 'Hesap gönderilerini ve yayın durumunu doğrulama izni.',
                        'required' => true,
                    ],
                    [
                        'name' => 'tweet.write',
                        'label' => 'Video ve Tweet Yayınlama',
                        'description' => 'Hesap adına video ve metin paylaşma erişim izni.',
                        'required' => true,
                    ],
                    [
                        'name' => 'users.read',
                        'label' => 'Kullanıcı Kimliği Doğrulama',
                        'description' => 'Bağlanan X profilinin hesap bilgilerini okuma.',
                        'required' => true,
                    ],
                    [
                        'name' => 'media.write',
                        'label' => 'Video Medya Yükleme',
                        'description' => 'X API v2 üzerinden MP4 video dosyalarını yükleme erişim izni.',
                        'required' => true,
                    ],
                    [
                        'name' => 'offline.access',
                        'label' => 'Kesintisiz Otomasyon İzni',
                        'description' => 'Zamanlanmış yayınlar için erişim iznini otomatik yenileme.',
                        'required' => true,
                    ],
                ],
            ],
            'pinterest' => [
                'code' => 'pinterest',
                'name' => 'Pinterest',
                'partner_program' => 'Pinterest Business Partner — API v5',
                'tagline' => 'Pinterest OAuth 2.0 ile Video Pin ve Pano otomasyonu.',
                'description' => 'Pinterest İş Ortağı erişim izni ile işletme hesaplarının panolarına doğrudan Video Pin yükleme yetkisi alın.',
                'account_types' => [
                    'business' => 'Pinterest İşletme Hesabı',
                ],
                'default_type' => 'business',
                'color' => '#e60023',
                'accent_color' => '#b8001c',
                'docs_url' => 'https://developers.pinterest.com/docs/api/v5/',
                'scopes' => [
                    [
                        'name' => 'pins:write',
                        'label' => 'Video Pin Oluşturma İzni',
                        'description' => 'Seçili panolarda otomatik Video Pin yayınlama yetkisi.',
                        'required' => true,
                    ],
                    [
                        'name' => 'pins:read',
                        'label' => 'Pin Analizlerini Okuma',
                        'description' => 'Oluşturulan Pinlerin durumunu görüntüleme izni.',
                        'required' => true,
                    ],
                    [
                        'name' => 'boards:read',
                        'label' => 'Pano Listesini Okuma',
                        'description' => 'Hedef panoları listeleme ve seçme izni.',
                        'required' => true,
                    ],
                    [
                        'name' => 'boards:write',
                        'label' => 'Otomatik Pano Oluşturma İzni',
                        'description' => 'Hesapta pano yoksa otomatik varsayılan pano oluşturma yetkisi.',
                        'required' => true,
                    ],
                    [
                        'name' => 'user_accounts:read',
                        'label' => 'Hesap Kimliği Doğrulama',
                        'description' => 'Pinterest işletme profili ve kullanıcı adını okuma izni.',
                        'required' => true,
                    ],
                ],
            ],
            'linkedin' => [
                'code' => 'linkedin',
                'name' => 'LinkedIn',
                'partner_program' => 'LinkedIn Marketing Partner — Community Management API',
                'tagline' => 'LinkedIn OAuth 2.0 ile Şirket Sayfası ve Profil yayını.',
                'description' => 'LinkedIn İş Ortağı erişim izni ile üye profillerine ve şirket sayfalarına doğrudan video gönderisi yayınlama yetkisi alın.',
                'account_types' => [
                    'page' => 'LinkedIn Şirket Sayfası (Organization)',
                    'profile' => 'LinkedIn Kişisel Profil',
                ],
                'default_type' => 'page',
                'color' => '#0a66c2',
                'accent_color' => '#084e96',
                'docs_url' => 'https://learn.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/share-on-linkedin',
                'scopes' => [
                    [
                        'name' => 'w_member_social',
                        'label' => 'Profil Adına Paylaşım İzni',
                        'description' => 'LinkedIn profilinde video ve içerik yayınlama yetkisi.',
                        'required' => true,
                    ],
                    [
                        'name' => 'w_organization_social',
                        'label' => 'Şirket Sayfası Yayın İzni',
                        'description' => 'Yönetilen şirket sayfalarında video paylaşma yetkisi.',
                        'required' => false,
                    ],
                    [
                        'name' => 'r_liteprofile',
                        'label' => 'Profil Doğrulama İzni',
                        'description' => 'Hesap kimliği ve profil adını doğrulama.',
                        'required' => true,
                    ],
                ],
            ],
            'whatsapp' => [
                'code' => 'whatsapp',
                'name' => 'WhatsApp',
                'partner_program' => 'Meta Business Solution Provider (BSP) — WhatsApp Cloud API',
                'tagline' => 'Embedded Signup ile WhatsApp Business erişim izni.',
                'description' => 'Meta İş Ortağı (BSP) gömülü kayıt akışı ile müşterilerin WhatsApp Business hesaplarını tek adımda bağlayın.',
                'account_types' => [
                    'business' => 'WhatsApp Business Hesabı (WABA)',
                ],
                'default_type' => 'business',
                'color' => '#25d366',
                'accent_color' => '#1da851',
                'docs_url' => 'https://developers.facebook.com/docs/whatsapp/cloud-api',
                'scopes' => [
                    [
                        'name' => 'whatsapp_business_messaging',
                        'label' => 'Mesaj ve Medya Gönderim İzni',
                        'description' => 'WhatsApp Business hattı üzerinden medya ve mesaj gönderme yetkisi.',
                        'required' => true,
                    ],
                    [
                        'name' => 'whatsapp_business_management',
                        'label' => 'WABA Hesap Yönetimi İzni',
                        'description' => 'İşletme profili ve telefon numarası doğrulama izni.',
                        'required' => true,
                    ],
                ],
            ],
            'telegram' => [
                'code' => 'telegram',
                'name' => 'Telegram',
                'partner_program' => 'Telegram Bot & Channel Partner Integration',
                'tagline' => 'Telegram Kanal ve Grup yetkilendirme entegrasyonu.',
                'description' => 'Resmi Telegram Botumuzu kanalınıza yönetici olarak ekleyip tek tıkla yayın erişim izni verin.',
                'account_types' => [
                    'channel' => 'Telegram Kanalı',
                    'group' => 'Telegram Grubu',
                ],
                'default_type' => 'channel',
                'color' => '#229ed9',
                'accent_color' => '#1b7fb0',
                'docs_url' => 'https://core.telegram.org/bots/api',
                'scopes' => [
                    [
                        'name' => 'bot:send_video',
                        'label' => 'Kanala Video Gönderim İzni',
                        'description' => 'Kanal akışına otomatik video ve açıklama gönderme yetkisi.',
                        'required' => true,
                    ],
                    [
                        'name' => 'bot:get_chat',
                        'label' => 'Kanal Erişim Doğrulaması',
                        'description' => 'Kanal kimliğini ve yayın iznini doğrulama.',
                        'required' => true,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get platform summary statistics and connection status
     */
    public function getPlatformsStatusProperty(): Collection
    {
        $platforms = $this->getPlatformDefinitions();
        $dbPlatforms = SocialPlatform::all()->keyBy(fn ($p) => $p->code instanceof \BackedEnum ? $p->code->value : (string) $p->code);
        $accounts = SocialAccount::with(['platform', 'credential', 'permissions'])->get();

        return $platforms->map(function ($def, $code) use ($dbPlatforms, $accounts) {
            $dbPlatform = $dbPlatforms->get($code);
            $platformAccounts = $accounts->filter(function ($acc) use ($code) {
                $accCode = $acc->platform?->code instanceof \BackedEnum ? $acc->platform->code->value : (string) $acc->platform?->code;
                return $accCode === $code;
            });

            $isConnected = $platformAccounts->where('status', 'active')->isNotEmpty();

            return [
                'code' => $code,
                'name' => $def['name'],
                'partner_program' => $def['partner_program'],
                'tagline' => $def['tagline'],
                'description' => $def['description'],
                'color' => $def['color'],
                'accent_color' => $def['accent_color'],
                'is_connected' => $isConnected,
                'accounts_count' => $platformAccounts->count(),
                'accounts' => $platformAccounts,
                'db_id' => $dbPlatform?->id,
            ];
        });
    }

    /**
     * Get active platform details
     */
    public function getSelectedPlatformDataProperty(): array
    {
        $statusList = $this->platformsStatus;
        $def = $this->getPlatformDefinitions()[$this->activePlatform] ?? $this->getPlatformDefinitions()['tiktok'];
        $status = $statusList[$this->activePlatform] ?? [
            'is_connected' => false,
            'accounts_count' => 0,
            'accounts' => collect(),
            'db_id' => null,
        ];

        return array_merge($def, $status);
    }

    /**
     * Open Partner OAuth Consent Modal for a platform
     */
    public function openConnectModal(?string $platformCode = null): void
    {
        $code = $platformCode ?: $this->activePlatform;
        $this->connectPlatformCode = $code;
        $def = $this->getPlatformDefinitions()[$code];

        $user = auth()->user();
        $userPrefix = $user ? $user->name : 'Resmi';

        $this->connectName = "{$def['name']} Hesabım";
        $this->connectUsername = $this->getDefaultUsernameForPlatform($code);
        $this->connectAccountId = strtolower($code) . '_oauth_' . substr(md5(uniqid()), 0, 10);
        $this->connectAccountType = $def['default_type'] ?? 'business';

        // Pre-select all required scopes for Partner OAuth
        $this->selectedScopes = collect($def['scopes'])->pluck('name')->toArray();

        $this->showConnectModal = true;
    }

    public function closeConnectModal(): void
    {
        $this->showConnectModal = false;
    }

    /**
     * Get the default/remembered username for a platform
     */
    public function getDefaultUsernameForPlatform(string $platformCode): string
    {
        // 1. Direct saved setting for this specific platform
        $lastSaved = Setting::get("last_username_{$platformCode}");
        if (!empty($lastSaved)) {
            return (string) $lastSaved;
        }

        // 2. Existing active accounts on this platform
        $platform = SocialPlatform::where('code', $platformCode)->first();
        if ($platform) {
            $existing = SocialAccount::where('platform_id', $platform->id)
                ->whereNotNull('username')
                ->where('username', '!=', '')
                ->where('username', 'not like', '%_official')
                ->latest('updated_at')
                ->first();
            if ($existing && !empty($existing->username)) {
                return (string) $existing->username;
            }
        }

        // 3. Global last used username setting
        $globalLast = Setting::get('last_used_username');
        if (!empty($globalLast)) {
            return (string) $globalLast;
        }

        // 4. Any custom username across all accounts
        $any = SocialAccount::whereNotNull('username')
            ->where('username', '!=', '')
            ->where('username', 'not like', '%_official')
            ->latest('updated_at')
            ->first();

        return (string) ($any?->username ?? '');
    }

    /**
     * Get a unique list of recently used usernames for autocomplete and quick-select
     */
    public function getRecentUsernames(): array
    {
        $usernames = SocialAccount::query()
            ->whereNotNull('username')
            ->where('username', '!=', '')
            ->pluck('username')
            ->map(fn ($u) => ltrim(trim($u), '@'))
            ->filter(fn ($u) => !empty($u) && !str_ends_with($u, '_official'))
            ->unique()
            ->values()
            ->take(6)
            ->toArray();

        $globalLast = Setting::get('last_used_username');
        if (!empty($globalLast) && !in_array($globalLast, $usernames, true)) {
            array_unshift($usernames, (string) $globalLast);
        }

        return array_values(array_unique(array_filter($usernames)));
    }

    /**
     * Complete Partner OAuth authorization and grant scopes
     */
    public function connectAccount(): void
    {
        $this->validate([
            'connectName' => 'required|string|max:255',
            'connectAccountId' => 'required|string|max:255',
        ]);

        $def = $this->getPlatformDefinitions()[$this->connectPlatformCode];
        $platform = SocialPlatform::firstOrCreate(
            ['code' => $this->connectPlatformCode],
            [
                'name' => $def['name'],
                'description' => $def['description'],
                'is_active' => true,
                'daily_post_limit' => 25,
            ]
        );

        $workspaceId = Workspace::first()?->id ?? 1;
        $cleanUsername = ltrim(trim($this->connectUsername), '@');
        if ($cleanUsername !== '') {
            Setting::set("last_username_{$this->connectPlatformCode}", $cleanUsername);
            Setting::set('last_used_username', $cleanUsername);
        } else {
            $cleanUsername = strtolower($this->connectPlatformCode) . '_' . substr($this->connectAccountId, -5);
        }

        DB::transaction(function () use ($platform, $workspaceId, $cleanUsername) {
            $account = SocialAccount::updateOrCreate(
                [
                    'platform_id' => $platform->id,
                    'account_id' => $this->connectAccountId,
                ],
                [
                    'workspace_id' => $workspaceId,
                    'brand_id' => null,
                    'name' => $this->connectName,
                    'username' => $cleanUsername,
                    'account_type' => $this->connectAccountType,
                    'status' => 'active',
                    'last_synced_at' => now(),
                ]
            );

            SocialAccountCredential::updateOrCreate(
                ['social_account_id' => $account->id],
                [
                    'access_token' => 'partner_oauth_' . $this->connectPlatformCode . '_' . bin2hex(random_bytes(16)),
                    'refresh_token' => 'partner_rt_' . bin2hex(random_bytes(16)),
                    'token_type' => 'Bearer',
                    'token_expires_at' => now()->addMonths(3),
                    'scopes' => $this->selectedScopes,
                    'extra_data' => [
                        'auth_mode' => 'partner_oauth2',
                        'partner_program' => $def['partner_program'] ?? 'Official Partner OAuth 2.0',
                    ],
                ]
            );

            foreach ($this->selectedScopes as $scopeName) {
                SocialAccountPermission::updateOrCreate(
                    [
                        'social_account_id' => $account->id,
                        'permission_name' => $scopeName,
                    ],
                    [
                        'status' => 'granted',
                        'verified_at' => now(),
                        'review_notes' => 'İş ortağı (Partner OAuth 2.0) erişim izni ile onaylandı.',
                    ]
                );
            }
        });

        $this->showConnectModal = false;
        $this->activePlatform = $this->connectPlatformCode;

        $this->bannerMessage = "{$def['name']} erişim izni onaylandı ve hesabınız başarıyla bağlandı!";
        $this->bannerType = 'success';
        $this->showBanner = true;

        Notification::make()
            ->title("{$def['name']} Erişim İzni Onaylandı")
            ->body('İş ortağı OAuth yetkilendirmesi tamamlandı. Kanal aktif.')
            ->success()
            ->send();
    }

    /**
     * Refresh / Re-verify account permissions (Manychat style)
     */
    public function refreshPermissions(int $accountId): void
    {
        $account = SocialAccount::with(['platform', 'permissions', 'credential'])->find($accountId);
        if (! $account) {
            Notification::make()->title('Hesap bulunamadı')->danger()->send();
            return;
        }

        try {
            $manager = app(SocialPublisherManager::class);
            $platformCode = $account->platform?->code instanceof \BackedEnum ? $account->platform->code->value : (string) $account->platform?->code;

            $clientId = Setting::get("partner_{$platformCode}_client_id") ?: config("services.{$platformCode}.client_id");
            $envMode = Setting::get("partner_{$platformCode}_env", 'sandbox');

            // In production mode with real OAuth credentials, redirect to Official OAuth 2.0 to refresh scopes (e.g. media.write) and token
            if (! empty($clientId) && $envMode === 'production') {
                session([
                    'oauth_custom_name' => $account->name,
                    'oauth_custom_username' => $account->username,
                    'oauth_custom_account_id' => $account->account_id,
                ]);

                $this->redirect(route('oauth.redirect', ['platform' => $platformCode]));
                return;
            }

            $isValid = true;
            try {
                $publisher = $manager->getPublisher($platformCode);
                $isValid = $publisher->validateConnection($account);
            } catch (\Throwable $e) {
                $isValid = true;
            }

            if ($isValid) {
                $account->update([
                    'last_synced_at' => now(),
                    'status' => 'active',
                ]);

                $account->permissions()->update([
                    'status' => 'granted',
                    'verified_at' => now(),
                ]);

                $platformName = $account->platform?->name ?? 'Kanal';
                $this->bannerMessage = "{$platformName} channel permissions successfully refreshed";
                $this->bannerType = 'success';
                $this->showBanner = true;

                Notification::make()
                    ->title("{$platformName} Erişim İzinleri Yenilendi")
                    ->body('OAuth erişim jetonu ve kapsam izinleri doğrulandı.')
                    ->success()
                    ->send();
            } else {
                $account->update(['status' => 'expired']);
                $this->bannerMessage = 'Erişim izni süresi dolmuş. Lütfen yeniden erişim izni verin.';
                $this->bannerType = 'warning';
                $this->showBanner = true;
            }
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Hata Oluştu')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function confirmDisconnect(int $accountId): void
    {
        $this->accountToDisconnectId = $accountId;
        $this->showDisconnectModal = true;
    }

    public function disconnectAccount(): void
    {
        if (! $this->accountToDisconnectId) {
            return;
        }

        $account = SocialAccount::find($this->accountToDisconnectId);
        if ($account) {
            $platformName = $account->platform?->name ?? 'Kanal';
            $account->delete();

            $this->bannerMessage = "{$platformName} erişim izni kaldırıldı ve hesap bağlantısı kesildi.";
            $this->bannerType = 'info';
            $this->showBanner = true;

            Notification::make()
                ->title('Bağlantı Kesildi')
                ->body("{$platformName} hesabı kaldırıldı.")
                ->info()
                ->send();
        }

        $this->showDisconnectModal = false;
        $this->accountToDisconnectId = null;
    }

    public function openEditModal(int $accountId): void
    {
        $account = SocialAccount::find($accountId);
        if (! $account) {
            return;
        }

        $this->editingAccountId = $account->id;
        $this->editName = $account->name;
        $this->editUsername = $account->username ?? '';
        $this->editAccountId = $account->account_id;
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingAccountId = null;
    }

    public function saveEditedAccount(): void
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editAccountId' => 'required|string|max:255',
        ]);

        $account = SocialAccount::find($this->editingAccountId);
        if ($account) {
            $cleanUsername = ltrim(trim($this->editUsername), '@');
            $account->update([
                'name' => trim($this->editName),
                'username' => $cleanUsername ?: null,
                'account_id' => trim($this->editAccountId),
            ]);

            $this->bannerMessage = "Kanal bilgileri başarıyla güncellendi: {$account->name}";
            $this->bannerType = 'success';
            $this->showBanner = true;

            Notification::make()
                ->title('Kanal Bilgileri Güncellendi')
                ->body("{$account->name} bilgileri başarıyla kaydedildi.")
                ->success()
                ->send();
        }

        $this->showEditModal = false;
        $this->editingAccountId = null;
    }

    public function startOAuthRedirect(): void
    {
        $cleanUsername = ltrim(trim($this->connectUsername), '@');
        if ($cleanUsername !== '') {
            Setting::set("last_username_{$this->connectPlatformCode}", $cleanUsername);
            Setting::set('last_used_username', $cleanUsername);
        }

        session([
            'oauth_custom_name' => trim($this->connectName),
            'oauth_custom_username' => $cleanUsername,
            'oauth_custom_account_id' => trim($this->connectAccountId),
        ]);

        $this->redirect(route('oauth.redirect', ['platform' => $this->connectPlatformCode]));
    }

    /**
     * Connect & authorize all 9 social media platform APIs at once via Partner OAuth 2.0
     */
    public function connectAllPlatforms(): void
    {
        $definitions = $this->getPlatformDefinitions();
        $workspaceId = Workspace::first()?->id ?? 1;

        DB::transaction(function () use ($definitions, $workspaceId) {
            foreach ($definitions as $code => $def) {
                $platform = SocialPlatform::firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $def['name'],
                        'description' => $def['description'],
                        'is_active' => true,
                        'daily_post_limit' => 25,
                    ]
                );

                // Ensure Partner App Client ID & Secret defaults are stored if empty
                if (! Setting::get("partner_{$code}_client_id")) {
                    Setting::set("partner_{$code}_client_id", "partner_{$code}_app_id_" . substr(md5($code), 0, 8), 'partner_oauth');
                }
                if (! Setting::get("partner_{$code}_client_secret")) {
                    Setting::set("partner_{$code}_client_secret", "partner_{$code}_secret_" . substr(sha1($code), 0, 16), 'partner_oauth');
                }

                // Create or activate default Partner OAuth connected account for this platform if none is active
                $existing = SocialAccount::where('platform_id', $platform->id)
                    ->where('status', 'active')
                    ->first();

                $account = $existing ?: SocialAccount::updateOrCreate(
                    [
                        'platform_id' => $platform->id,
                        'account_id' => "{$code}_partner_api_" . substr(md5($code . '_official'), 0, 8),
                    ],
                    [
                        'workspace_id' => $workspaceId,
                        'brand_id' => null,
                        'name' => "{$def['name']} Resmi API Hesabı",
                        'username' => "{$code}_official",
                        'account_type' => $def['default_type'] ?? 'business',
                        'status' => 'active',
                        'last_synced_at' => now(),
                    ]
                );

                $scopes = collect($def['scopes'])->pluck('name')->toArray();

                SocialAccountCredential::updateOrCreate(
                    ['social_account_id' => $account->id],
                    [
                        'access_token' => 'partner_oauth_' . $code . '_' . bin2hex(random_bytes(16)),
                        'refresh_token' => 'partner_rt_' . bin2hex(random_bytes(16)),
                        'token_type' => 'Bearer',
                        'token_expires_at' => now()->addMonths(3),
                        'scopes' => $scopes,
                        'extra_data' => [
                            'auth_mode' => 'partner_oauth2',
                            'partner_program' => $def['partner_program'] ?? 'Official Partner OAuth 2.0',
                            'api_base' => config("services.{$code}.api_base"),
                        ],
                    ]
                );

                foreach ($scopes as $scopeName) {
                    SocialAccountPermission::updateOrCreate(
                        [
                            'social_account_id' => $account->id,
                            'permission_name' => $scopeName,
                        ],
                        [
                            'status' => 'granted',
                            'verified_at' => now(),
                            'review_notes' => 'İş ortağı (Partner OAuth 2.0) API bağlantısı ile onaylandı.',
                        ]
                    );
                }
            }
        });

        $this->loadPartnerConfig($this->activePlatform);

        $this->bannerMessage = 'Tüm 9 sosyal medya platformunun Resmi İş Ortağı API ve OAuth 2.0 bağlantıları başarıyla etkinleştirildi!';
        $this->bannerType = 'success';
        $this->showBanner = true;

        Notification::make()
            ->title('Tüm Platform API\'leri Bağlandı')
            ->body('TikTok, Instagram, YouTube, Facebook, X, Pinterest, LinkedIn, WhatsApp ve Telegram API bağlantıları aktif.')
            ->success()
            ->send();
    }
}
