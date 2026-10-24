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

    public function mount(): void
    {
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
            $modeText = $result->isTestMode ? ' (Test Modu Şablonu)' : ' (' . $result->modelUsed . ')';
            Notification::make()
                ->title("{$availableCount} Platform İçin İçerik Başarıyla Üretildi" . $modeText)
                ->body('Sosyal medya kanallarınız için içerik ve hashtag taslakları hazırlandı.')
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

    public function savePlatformContent(string $platformCode): void
    {
        if (! $this->selectedVideoId || empty($this->platformContents[$platformCode])) {
            return;
        }

        $data = $this->platformContents[$platformCode];
        $hashtagsArray = array_filter(array_map('trim', explode(' ', str_replace(['#', ','], ' ', $data['hashtags']))));

        AiContentGeneration::updateOrCreate(
            ['video_id' => $this->selectedVideoId, 'platform_code' => $platformCode],
            [
                'title' => $data['title'],
                'hook_sentence' => $data['hook_sentence'],
                'caption' => $data['caption'],
                'hashtags' => array_values($hashtagsArray),
                'call_to_action' => $data['call_to_action'],
                'suggested_product_url' => $data['suggested_product_url'],
                'status' => $data['status'] ?? 'approved',
            ]
        );

        Notification::make()
            ->title(ucfirst($platformCode) . ' içeriği kaydedildi')
            ->success()
            ->send();
    }

    public function getSelectedVideoProperty(): ?Video
    {
        return $this->selectedVideoId ? Video::find($this->selectedVideoId) : null;
    }
}
