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

    public function mount(): void
    {
        $firstVideo = Video::latest()->first();
        if ($firstVideo) {
            $this->selectedVideoId = $firstVideo->id;
            $this->loadVideoContents();
        }
    }

    public function updatedSelectedVideoId(): void
    {
        $this->loadVideoContents();
    }

    public function loadVideoContents(): void
    {
        if (! $this->selectedVideoId) {
            return;
        }

        $generations = AiContentGeneration::where('video_id', $this->selectedVideoId)->get();

        $platformCodes = ['instagram', 'facebook', 'youtube', 'x', 'pinterest', 'tiktok', 'linkedin'];
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

            $modeText = $result->isTestMode ? ' (Test Modu Şablonu)' : ' (' . $result->modelUsed . ')';
            Notification::make()
                ->title('7 Platform İçin İçerik Başarıyla Üretildi' . $modeText)
                ->body('Instagram, Facebook, YouTube, X, Pinterest, TikTok ve LinkedIn taslakları hazırlandı.')
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
