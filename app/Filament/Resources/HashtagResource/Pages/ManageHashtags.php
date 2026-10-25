<?php

namespace App\Filament\Resources\HashtagResource\Pages;

use App\Filament\Resources\HashtagResource;
use App\Services\SEO\TrendingHashtagService;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageHashtags extends ManageRecords
{
    protected static string $resource = HashtagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 1. Tekil Hashtag Ekle
            Actions\CreateAction::make()
                ->label('Tekil Ekle')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->size('sm')
                ->modalWidth(\Filament\Support\Enums\MaxWidth::TwoExtraLarge)
                ->mutateFormDataUsing(function (array $data) {
                    $data['workspace_id'] = 1;
                    return $data;
                }),

            // 2. Yapay Zeka ile Trend Keşfet
            Actions\Action::make('ai_trend_scout')
                ->label('AI Trend Keşfet')
                ->icon('heroicon-o-sparkles')
                ->color('info')
                ->size('sm')
                ->modalWidth(\Filament\Support\Enums\MaxWidth::TwoExtraLarge)
                ->modalHeading('Yapay Zeka ile Platform Trendlerini Keşfet')
                ->modalDescription('Seçtiğiniz platformun o anki en güncel algoritmasında viral olan revaçtaki Türkçe ve global hashtagleri AI otomatik üretir ve havuza ekler.')
                ->form([
                    Select::make('platform_code')
                        ->label('Hangi Platform İçin Trend Keşfedilsin?')
                        ->options([
                            'instagram' => 'Instagram (Reels ve Keşfet algoritmaları)',
                            'tiktok' => 'TikTok (FYP ve Viral akımlar)',
                            'youtube' => 'YouTube Shorts (Öne çıkan trendler)',
                            'x' => 'X / Twitter (Gündem ve etkileşim)',
                            'pinterest' => 'Pinterest (İlham ve arama niyetleri)',
                            'facebook' => 'Facebook (Topluluk ve video etkileşimi)',
                            'linkedin' => 'LinkedIn (E-ticaret ve sektörel trendler)',
                        ])
                        ->default('tiktok')
                        ->required(),

                    TextInput::make('topic')
                        ->label('Özel Odak / Konu (İsteğe Bağlı)')
                        ->placeholder('Örn: Patenli ayakkabı, çocuk eğlencesi, hediye fikirleri, sokak modası')
                        ->helperText('Boş bırakırsanız marka ve sektörünüze en uygun genel trendler taranır.'),

                    Select::make('count')
                        ->label('Üretilecek Trend Sayısı')
                        ->options([
                            15 => '15 Trend Hashtag',
                            20 => '20 Trend Hashtag',
                            25 => '25 Trend Hashtag',
                            30 => '30 Trend Hashtag',
                        ])
                        ->default(25)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $service = app(TrendingHashtagService::class);
                    $platform = $data['platform_code'];
                    $topic = $data['topic'] ?? null;
                    $count = (int) ($data['count'] ?? 25);

                    $generatedTags = $service->generateTrendingWithAi($platform, $topic, $count);

                    if (empty($generatedTags)) {
                        Notification::make()
                            ->title('Trend etiket üretilemedi')
                            ->danger()
                            ->send();
                        return;
                    }

                    $result = $service->bulkImport(
                        tagsInput: $generatedTags,
                        platformCode: $platform,
                        category: 'trending',
                        priority: 9,
                        isTrending: true
                    );

                    Notification::make()
                        ->title(strtoupper($platform) . " İçin {$result['total_processed']} Trend Hashtag Havuza Eklendi!")
                        ->body("Eklenen etiketlerden bazıları: #" . implode(' #', array_slice($result['tags'], 0, 6)) . "...")
                        ->success()
                        ->duration(8000)
                        ->send();
                }),

            // 3. Revaçtaki Hashtagleri Toplu Ekle
            Actions\Action::make('bulk_add_trending')
                ->label('Toplu Ekle')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->size('sm')
                ->modalWidth(\Filament\Support\Enums\MaxWidth::TwoExtraLarge)
                ->modalHeading('Platforma Özel Revaçtaki Hashtagleri Toplu Ekle')
                ->modalDescription('Virgülle, boşlukla veya alt alta onlarca hashtag yapıştırabilirsiniz. # işaretleri ve mükerrerler otomatik temizlenir.')
                ->form([
                    Select::make('platform_filter')
                        ->label('Hedef Sosyal Medya Platformu')
                        ->options([
                            '' => '🌐 Tüm Platformlar (Global)',
                            'instagram' => 'Instagram (Reels & Keşfet)',
                            'tiktok' => 'TikTok (FYP & Viral)',
                            'youtube' => 'YouTube (Shorts & Video)',
                            'x' => 'X (Twitter - Gündem)',
                            'pinterest' => 'Pinterest (İlham & Pano)',
                            'facebook' => 'Facebook',
                            'linkedin' => 'LinkedIn',
                            'telegram' => 'Telegram',
                            'whatsapp' => 'WhatsApp',
                        ])
                        ->default('')
                        ->helperText('Bu hashtaglerin sadece bu platformda kullanılmasını istiyorsanız seçin.'),

                    Textarea::make('tags_text')
                        ->label('Revaçtaki Hashtag Listesi')
                        ->placeholder("Örnek:\n#kesfet #reelsviral #patenliayakkabi #cocukmodasi #fyp #trendvideolar\nya da virgülle ayırarak yapıştırabilirsiniz.")
                        ->rows(6)
                        ->required(),

                    Grid::make(3)->schema([
                        Select::make('category')
                            ->label('Kategori')
                            ->options([
                                'trending' => '🔥 Revaçta / Trend',
                                'brand' => '🏷️ Marka',
                                'product' => '👟 Ürün',
                                'kids' => '👶 Çocuk & Hediye',
                                'general' => '🌟 Genel',
                            ])
                            ->default('trending')
                            ->required(),

                        TextInput::make('priority')
                            ->label('Öncelik Puanı')
                            ->numeric()
                            ->default(9)
                            ->required(),

                        DateTimePicker::make('expires_at')
                            ->label('Trend Bitiş Tarihi (İsteğe Bağlı)')
                            ->helperText('Boş bırakılırsa süresiz aktif kalır.'),
                    ]),
                ])
                ->action(function (array $data) {
                    $service = app(TrendingHashtagService::class);
                    $result = $service->bulkImport(
                        tagsInput: $data['tags_text'],
                        platformCode: ! empty($data['platform_filter']) ? $data['platform_filter'] : null,
                        category: $data['category'],
                        priority: (int) $data['priority'],
                        expiresAt: ! empty($data['expires_at']) ? \Carbon\Carbon::parse($data['expires_at']) : null,
                        isTrending: true
                    );

                    Notification::make()
                        ->title("Hashtag Havuzu Güncellendi")
                        ->body("Toplam {$result['total_processed']} etiket işlendi: {$result['added_count']} yeni etiket eklendi, {$result['existing_count']} mevcut etiket trend olarak güncellendi.")
                        ->success()
                        ->send();
                }),

            // 4. Hazır Trend Paketi Yükle
            Actions\Action::make('load_preset_packs')
                ->label('Hazır Paket')
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->modalWidth(\Filament\Support\Enums\MaxWidth::TwoExtraLarge)
                ->modalHeading('Platforma Özel Hazır Trend Paketini Yükle')
                ->modalDescription('Sosyal medya algoritmalarına özel olarak test edilmiş hazır revaçtaki etiket paketlerini tek tıkla havuza dahil edin.')
                ->form([
                    Select::make('preset_key')
                        ->label('Yüklenecek Trend Paketi')
                        ->options(function () {
                            $presets = app(TrendingHashtagService::class)->getPresetPacks();
                            $options = [];
                            foreach ($presets as $key => $pack) {
                                $options[$key] = "{$pack['name']} ({$pack['description']})";
                            }
                            return $options;
                        })
                        ->required(),
                ])
                ->action(function (array $data) {
                    $service = app(TrendingHashtagService::class);
                    $result = $service->loadPreset($data['preset_key']);

                    Notification::make()
                        ->title('Trend Paketi Başarıyla Yüklendi')
                        ->body("Toplam {$result['total_processed']} trend etiketi havuza aktarıldı.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
