<?php

namespace App\Filament\Pages;

use App\Models\AiContentGeneration;
use App\Models\Brand;
use App\Models\Video;
use App\Services\AI\Contracts\AiContentProviderInterface;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AiStudio extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationGroup = 'AI ve SEO Stüdyosu';
    protected static ?string $navigationLabel = 'AI İçerik Stüdyosu';
    protected static ?string $title = 'AI İçerik ve Sosyal SEO Stüdyosu';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.ai-studio';

    public ?int $selectedVideoId = null;
    public string $activeTab = 'instagram';

    public array $platformContents = [];
    public bool $isTestMode = false;
    public ?string $modelUsed = null;
    public bool $onlyConnected = true;
    public ?string $openAiKeyInput = null;
    public ?string $openAiModelInput = 'gpt-4o-mini';
    public bool $showApiKeyModal = false;

    public function mount(): void
    {
        $this->openAiModelInput = config('services.openai.model') ?: \App\Models\Setting::get('openai_model', 'gpt-4o-mini');

        $firstVideo = Video::orderBy('id', 'asc')->first();
        if ($firstVideo) {
            $this->selectedVideoId = $firstVideo->id;
        }

        $available = $this->availablePlatforms;
        if (! empty($available)) {
            $this->activeTab = array_key_first($available);
        }

        if ($this->selectedVideoId) {
            $this->loadVideoContents();
        }
    }

    public function getIsOpenAiConfiguredProperty(): bool
    {
        $key = config('services.openai.api_key') ?: env('OPENAI_API_KEY') ?: \App\Models\Setting::get('openai_api_key');
        return ! empty($key) && ! str_starts_with($key, 'mock_');
    }

    public function getMaskedApiKeyProperty(): ?string
    {
        $key = config('services.openai.api_key') ?: env('OPENAI_API_KEY') ?: \App\Models\Setting::get('openai_api_key');
        if (empty($key)) {
            return null;
        }

        if (strlen($key) <= 8) {
            return '••••••••';
        }

        return substr($key, 0, 7) . '••••••••' . substr($key, -4);
    }

    public function saveOpenAiSettings(): void
    {
        if (! empty($this->openAiKeyInput)) {
            \App\Models\Setting::set('openai_api_key', trim($this->openAiKeyInput));
        }

        if (! empty($this->openAiModelInput)) {
            \App\Models\Setting::set('openai_model', trim($this->openAiModelInput));
        }

        $this->openAiKeyInput = null;
        $this->showApiKeyModal = false;

        Notification::make()
            ->title('OpenAI Ayarları Kaydedildi')
            ->body('API anahtarı ve model yapılandırması başarıyla güncellendi.')
            ->success()
            ->send();
    }

    public function updatedSelectedVideoId(): void
    {
        $this->loadVideoContents();
    }

    public function updatedOnlyConnected(): void
    {
        $available = $this->availablePlatforms;
        if (! isset($available[$this->activeTab]) && ! empty($available)) {
            $this->activeTab = array_key_first($available);
        }
        $this->loadVideoContents();
    }

    /**
     * Get available platforms filtered by connection status if onlyConnected is active
     */
    public function getAvailablePlatformsProperty(): array
    {
        $allPlatforms = [
            'instagram' => ['name' => 'Instagram Reels', 'icon' => 'heroicon-m-camera'],
            'facebook' => ['name' => 'Facebook', 'icon' => 'heroicon-m-globe-alt'],
            'youtube' => ['name' => 'YouTube Shorts', 'icon' => 'heroicon-m-play-circle'],
            'x' => ['name' => 'X (Twitter)', 'icon' => 'heroicon-m-chat-bubble-bottom-center-text'],
            'pinterest' => ['name' => 'Pinterest Pin', 'icon' => 'heroicon-m-bookmark'],
            'tiktok' => ['name' => 'TikTok', 'icon' => 'heroicon-m-sparkles'],
            'linkedin' => ['name' => 'LinkedIn Post', 'icon' => 'heroicon-m-briefcase'],
        ];

        $connectedAccounts = \App\Models\SocialAccount::where('status', 'active')
            ->with('platform')
            ->get();

        $connectedCodes = $connectedAccounts->map(function ($acc) {
            return $acc->platform?->code instanceof \BackedEnum
                ? $acc->platform->code->value
                : (string) $acc->platform?->code;
        })->filter()->unique()->values()->toArray();

        $result = [];
        foreach ($allPlatforms as $code => $info) {
            $isConnected = in_array($code, $connectedCodes, true);
            $acc = $isConnected ? $connectedAccounts->first(function ($a) use ($code) {
                $c = $a->platform?->code instanceof \BackedEnum ? $a->platform->code->value : (string) $a->platform?->code;
                return $c === $code;
            }) : null;

            // Eğer "Yalnızca Bağlı Hesaplar" modu açıksa ve hesap bağlı değilse atla
            if ($this->onlyConnected && ! $isConnected) {
                continue;
            }

            $result[$code] = array_merge($info, [
                'is_connected' => $isConnected,
                'account_name' => $acc?->name ?? null,
                'username' => $acc?->username ?? null,
            ]);
        }

        // Eğer sadece bağlı hesaplar seçiliyken henüz hiç bağlı hesap yoksa, ekranın boş kalmaması için tümünü bağlantısız göster
        if (empty($result) && $this->onlyConnected) {
            foreach ($allPlatforms as $code => $info) {
                $result[$code] = array_merge($info, [
                    'is_connected' => false,
                    'account_name' => null,
                    'username' => null,
                ]);
            }
        }

        return $result;
    }

    public function loadVideoContents(): void
    {
        if (! $this->selectedVideoId) {
            return;
        }

        $generations = AiContentGeneration::where('video_id', $this->selectedVideoId)->get();

        $platformCodes = array_keys($this->availablePlatforms);
        $this->platformContents = [];

        foreach ($platformCodes as $code) {
            $gen = $generations->firstWhere('platform_code', $code);

            if ($gen) {
                $this->platformContents[$code] = [
                    'id' => $gen->id,
                    'title' => $gen->title,
                    'hook_sentence' => $gen->hook_sentence,
                    'caption' => $gen->caption,
                    'hashtags' => is_array($gen->hashtags) ? implode(' ', array_map(fn ($t) => '#' . ltrim($t, '#'), $gen->hashtags)) : '',
                    'call_to_action' => $gen->call_to_action,
                    'suggested_product_url' => $gen->suggested_product_url,
                    'status' => $gen->status,
                    'ai_model' => $gen->ai_model,
                ];
            } else {
                $this->platformContents[$code] = [
                    'id' => null,
                    'title' => '',
                    'hook_sentence' => '',
                    'caption' => '',
                    'hashtags' => '',
                    'call_to_action' => '',
                    'suggested_product_url' => '',
                    'status' => 'draft',
                    'ai_model' => null,
                ];
            }
        }
    }

    public function generateAiContent(): void
    {
        if (! $this->selectedVideoId) {
            Notification::make()->title('Lütfen önce bir video seçin')->warning()->send();
            return;
        }

        $video = Video::find($this->selectedVideoId);
        if (! $video) {
            Notification::make()->title('Video bulunamadı')->danger()->send();
            return;
        }

        /** @var AiContentProviderInterface $service */
        $service = app(AiContentProviderInterface::class);

        try {
            $result = $service->generateForVideo($video);
            $this->isTestMode = $result->isTestMode;
            $this->modelUsed = $result->modelUsed;

            $this->loadVideoContents();

            $availableCount = count($this->availablePlatforms);
            Notification::make()
                ->title("{$availableCount} Platform İçin Akıllı İçerik Üretildi ✨")
                ->body('Tüm bağlı sosyal medya kanallarınız için özgün açıklama, kanca ve SEO etiketleri hazırlandı.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('AI Üretim Hatası')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function previousVideo(): void
    {
        if (! $this->selectedVideoId) {
            return;
        }

        $prev = Video::where('id', '<', $this->selectedVideoId)->orderBy('id', 'desc')->first();
        if ($prev) {
            $this->selectedVideoId = $prev->id;
            $this->loadVideoContents();
        }
    }

    public function nextVideo(): void
    {
        if (! $this->selectedVideoId) {
            return;
        }

        $next = Video::where('id', '>', $this->selectedVideoId)->orderBy('id', 'asc')->first();
        if ($next) {
            $this->selectedVideoId = $next->id;
            $this->loadVideoContents();
        }
    }

    public function getHasPreviousVideoProperty(): bool
    {
        return $this->selectedVideoId ? Video::where('id', '<', $this->selectedVideoId)->exists() : false;
    }

    public function getHasNextVideoProperty(): bool
    {
        return $this->selectedVideoId ? Video::where('id', '>', $this->selectedVideoId)->exists() : false;
    }

    public function getVideoListProperty()
    {
        return Video::orderBy('id', 'asc')->get();
    }

    public function savePlatformContent(string $platformCode, bool $notify = true): void
    {
        if (! $this->selectedVideoId || empty($this->platformContents[$platformCode])) {
            return;
        }

        $data = $this->platformContents[$platformCode];
        $hashtagsArray = array_filter(array_map('trim', explode(' ', str_replace(['#', ','], ' ', $data['hashtags'] ?? ''))));

        AiContentGeneration::updateOrCreate(
            ['video_id' => $this->selectedVideoId, 'platform_code' => $platformCode],
            [
                'title' => $data['title'] ?? null,
                'hook_sentence' => $data['hook_sentence'] ?? null,
                'caption' => $data['caption'] ?? '',
                'hashtags' => array_values($hashtagsArray),
                'call_to_action' => $data['call_to_action'] ?? null,
                'suggested_product_url' => $data['suggested_product_url'] ?? null,
                'status' => $data['status'] ?? 'approved',
            ]
        );

        if ($notify) {
            Notification::make()
                ->title(ucfirst($platformCode) . ' içeriği kaydedildi')
                ->success()
                ->send();
        }
    }

    public function saveAllPlatformContents(): void
    {
        if (! $this->selectedVideoId) {
            return;
        }

        foreach (array_keys($this->availablePlatforms) as $platformCode) {
            if (! empty($this->platformContents[$platformCode])) {
                $this->savePlatformContent($platformCode, false);
            }
        }

        Notification::make()
            ->title('Tüm Platform İçerikleri Kaydedildi')
            ->body('Aktif tüm kanallardaki metinler ve durumlar başarıyla güncellendi.')
            ->success()
            ->send();
    }

    public function getSelectedVideoProperty(): ?Video
    {
        return $this->selectedVideoId ? Video::find($this->selectedVideoId) : null;
    }

    /**
     * Get platform-specific UI fields, character limits and structural definitions
     */
    public function getPlatformConfig(string $platform): array
    {
        return match ($platform) {
            'youtube' => [
                'has_title' => true,
                'title_label' => 'Video / Shorts Başlığı (SEO)',
                'title_placeholder' => 'Arama odaklı çarpıcı başlık (Zorunlu)...',
                'title_max' => 100,
                'title_required' => true,
                'caption_label' => 'Video Açıklaması (Description)',
                'caption_placeholder' => 'Video açıklaması, zaman damgaları ve detaylar...',
                'caption_max' => 5000,
                'has_hook' => true,
                'hook_label' => 'Shorts İlk 3 Saniye Kancası (Giriş)',
                'hook_placeholder' => 'İzleyiciyi videoda tutacak ilk 3 saniye kancası...',
                'has_hashtags' => true,
                'hashtags_label' => 'YouTube Etiketleri & Hashtag\'ler',
                'hashtags_placeholder' => '#Shorts #patenliayakkabi #trend',
                'has_cta' => true,
                'cta_label' => 'Call To Action (Abone Ol / Beğen)',
                'has_url' => true,
                'url_label' => 'Açıklama İçi Tıklanabilir Bağlantı (URL)',
                'url_note' => 'YouTube video açıklamasında bağlantılar doğrudan tıklanabilir olarak açılır.',
            ],
            'pinterest' => [
                'has_title' => true,
                'title_label' => 'Pin Başlığı',
                'title_placeholder' => 'Arama niyetine uygun Pin başlığı (Max 100 karakter)...',
                'title_max' => 100,
                'title_required' => true,
                'caption_label' => 'Pin Açıklaması (Description)',
                'caption_placeholder' => 'Görsel arama motoru optimizasyonlu Pin açıklaması...',
                'caption_max' => 500,
                'has_hook' => false,
                'hook_label' => '',
                'hook_placeholder' => '',
                'has_hashtags' => true,
                'hashtags_label' => 'Pin Anahtar Kelimeleri & Etiketler',
                'hashtags_placeholder' => '#patenliayakkabi #cocukmodasi #hediyefikri',
                'has_cta' => true,
                'cta_label' => 'Call To Action (CTA)',
                'has_url' => true,
                'url_label' => 'Hedef Web Sitesi Bağlantısı (Landing URL - Zorunlu)',
                'url_note' => 'Pinterest kullanıcıları pin tıklandığında doğrudan bu web adresine yönlendirilir.',
            ],
            'facebook' => [
                'has_title' => true,
                'title_label' => 'Video Başlığı (Opsiyonel)',
                'title_placeholder' => 'Facebook video başlığı...',
                'title_max' => 255,
                'title_required' => false,
                'caption_label' => 'Gönderi / Reels Açıklaması',
                'caption_placeholder' => 'Gönderi metni ve detaylar...',
                'caption_max' => 60000,
                'has_hook' => true,
                'hook_label' => 'Dikkat Çekici Giriş Cümlesi (Hook)',
                'hook_placeholder' => 'İlk 3 saniye kancası...',
                'has_hashtags' => true,
                'hashtags_label' => 'Hashtag\'ler',
                'hashtags_placeholder' => '#patenliayakkabi #reels #trend',
                'has_cta' => true,
                'cta_label' => 'Call To Action (CTA)',
                'has_url' => true,
                'url_label' => 'Tıklanabilir Web Sitesi / Satın Alma Linki',
                'url_note' => 'Facebook gönderi metninde web bağlantıları tıklanabilir olarak görünür.',
            ],
            'instagram' => [
                'has_title' => false, // Instagram'da BAŞLIK YOKTUR
                'caption_label' => 'Reels Açıklama Metni (Caption)',
                'caption_placeholder' => 'Instagram Reels açıklaması ve kancası...',
                'caption_max' => 2200,
                'has_hook' => true,
                'hook_label' => 'Reels İlk 3 Saniye Kancası (Video Üstü / İlk Satır)',
                'hook_placeholder' => 'Reels videosunun ilk 3 saniyesinde ekrana yazılacak veya seslendirilecek kanca...',
                'has_hashtags' => true,
                'hashtags_label' => 'Hashtag Havuzu (3-5 İdeal Etiket)',
                'hashtags_placeholder' => '#reels #patenliayakkabi #kesfet #cocukmodasi',
                'has_cta' => true,
                'cta_label' => 'Call To Action (Profil / Yorum Yönlendirmesi)',
                'has_url' => true,
                'url_label' => 'Bio Linki / Yönlendirilen Web Sayfası',
                'url_note' => 'Instagram Reels açıklamalarında linkler tıklanamaz. "Bio\'daki linke tıkla" yönlendirmesi kullanılır.',
            ],
            'x' => [
                'has_title' => false, // X (Twitter)'da BAŞLIK YOKTUR
                'caption_label' => 'Tweet Metni (Gönderi)',
                'caption_placeholder' => 'Çarpıcı, merak uyandıran 280 karakterlik tweet metni...',
                'caption_max' => 280,
                'has_hook' => true,
                'hook_label' => 'Vurucu Başlangıç Cümlesi (İlk Cümle)',
                'hook_placeholder' => 'Tweetin ilk dikkat çeken açılış cümlesi...',
                'has_hashtags' => true,
                'hashtags_label' => 'Tweet Hashtag\'leri (1-2 Kısa Etiket)',
                'hashtags_placeholder' => '#PatenliAyakkabi #Trend',
                'has_cta' => true,
                'cta_label' => 'Call To Action (CTA)',
                'has_url' => true,
                'url_label' => 'Tweet İçi Tıklanabilir Bağlantı (URL)',
                'url_note' => 'Eklenen link 280 karakterlik sınıra dahil edilir.',
            ],
            'tiktok' => [
                'has_title' => false, // TikTok'ta BAŞLIK YOKTUR
                'caption_label' => 'Video Açıklaması (Caption)',
                'caption_placeholder' => 'TikTok video açıklaması, trend kelimeler ve etkileşim sorusu...',
                'caption_max' => 2200,
                'has_hook' => true,
                'hook_label' => 'İlk 3 Saniye Kancası (Video Üstü Metin)',
                'hook_placeholder' => 'TikTok izleyicisini tutacak merak uyandırıcı ilk 3 saniye kancası...',
                'has_hashtags' => true,
                'hashtags_label' => 'TikTok Trend Hashtag\'leri (#fyp #kesfet)',
                'hashtags_placeholder' => '#fyp #kesfet #patenliayakkabi #foryou',
                'has_cta' => true,
                'cta_label' => 'Call To Action (Takip Et / Yorum Yap)',
                'has_url' => true,
                'url_label' => 'Biyografi Linki (Web Sitesi)',
                'url_note' => 'TikTok video açıklamalarında bağlantılar tıklanamaz, profile yönlendirilir.',
            ],
            'linkedin' => [
                'has_title' => false, // LinkedIn normal gönderide BAŞLIK YOKTUR
                'caption_label' => 'Gönderi Metni (Post Commentary)',
                'caption_placeholder' => 'Profesyonel değer odaklı LinkedIn gönderi metni...',
                'caption_max' => 3000,
                'has_hook' => true,
                'hook_label' => 'Giriş Kancası ("Daha Fazlasını Gör" Öncesi İlk 2 Satır)',
                'hook_placeholder' => 'LinkedIn akışında dikkat çeken açılış cümlesi...',
                'has_hashtags' => true,
                'hashtags_label' => 'Profesyonel Hashtag\'ler (3-4 Adet)',
                'hashtags_placeholder' => '#eticaret #inovasyon #girisimcilik',
                'has_cta' => true,
                'cta_label' => 'Call To Action (CTA)',
                'has_url' => true,
                'url_label' => 'Tıklanabilir Bağlantı / Ürün Linki',
                'url_note' => 'LinkedIn gönderilerinde bağlantılar tıklanabilir önizleme kartı olarak açılır.',
            ],
            default => [
                'has_title' => false,
                'caption_label' => 'Açıklama Metni',
                'caption_placeholder' => 'İçerik açıklaması...',
                'caption_max' => 2200,
                'has_hook' => true,
                'hook_label' => 'Giriş Cümlesi (Hook)',
                'hook_placeholder' => 'İlk cümle...',
                'has_hashtags' => true,
                'hashtags_label' => 'Hashtag\'ler',
                'hashtags_placeholder' => '#etiket',
                'has_cta' => true,
                'cta_label' => 'Call To Action',
                'has_url' => true,
                'url_label' => 'Hedef URL',
                'url_note' => null,
            ],
        };
    }
}
