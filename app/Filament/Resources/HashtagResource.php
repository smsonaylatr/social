<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HashtagResource\Pages;
use App\Models\Hashtag;
use App\Models\HashtagGroup;
use App\Models\Workspace;
use App\Services\SEO\TrendingHashtagService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class HashtagResource extends Resource
{
    protected static ?string $model = Hashtag::class;

    protected static ?string $navigationIcon = 'heroicon-o-hashtag';
    protected static ?string $navigationGroup = 'AI ve SEO Stüdyosu';
    protected static ?string $navigationLabel = 'Hashtag Havuzu';
    protected static ?string $modelLabel = 'Hashtag';
    protected static ?string $pluralModelLabel = 'Hashtag Havuzu';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('tag')
                        ->label('Hashtag')
                        ->placeholder('Örn: patenliayakkabi')
                        ->required()
                        ->maxLength(255)
                        ->mutateDehydratedStateUsing(fn ($state) => ltrim(trim($state), '#')),

                    Select::make('category')
                        ->label('Kategori')
                        ->options([
                            'trending' => '🔥 Revaçta / Trend',
                            'brand' => '🏷️ Marka',
                            'product' => '👟 Ürün & Model',
                            'kids' => '👶 Çocuk & Hediye',
                            'general' => '🌟 Genel Eğlence',
                            'campaign' => '🎁 Kampanya / Sezonluk',
                        ])
                        ->default('trending')
                        ->required(),

                    Select::make('group_id')
                        ->label('Hashtag Grubu')
                        ->relationship('group', 'name')
                        ->searchable()
                        ->preload(),

                    Select::make('platform_filter')
                        ->label('Platform Kısıtı')
                        ->options([
                            'instagram' => 'Instagram',
                            'tiktok' => 'TikTok',
                            'youtube' => 'YouTube',
                            'x' => 'X (Twitter)',
                            'pinterest' => 'Pinterest',
                            'facebook' => 'Facebook',
                            'linkedin' => 'LinkedIn',
                            'telegram' => 'Telegram',
                            'whatsapp' => 'WhatsApp',
                        ])
                        ->placeholder('Tüm Platformlarda Geçerli (Global)'),

                    TextInput::make('priority')
                        ->label('Öncelik Puanı (1-10)')
                        ->numeric()
                        ->default(9)
                        ->required(),

                    DateTimePicker::make('expires_at')
                        ->label('Trend Bitiş Tarihi (Opsiyonel)')
                        ->helperText('Dönemsel trendler için belirlenen tarihten sonra otomatik pasife alınır.'),

                    Toggle::make('is_trending')
                        ->label('Revaçtaki (Trend / Viral) Etiket')
                        ->default(true),

                    Toggle::make('is_active')
                        ->label('Aktif')
                        ->default(true),

                    Toggle::make('is_blacklisted')
                        ->label('Kara Listeye Al (Yayınlarda Engelle)')
                        ->default(false),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('tag')
                    ->label('Hashtag')
                    ->formatStateUsing(fn (string $state): string => '#' . ltrim($state, '#'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('bold')
                    ->color(fn (Hashtag $record) => $record->is_trending ? 'warning' : null),

                IconColumn::make('is_trending')
                    ->label('Trend')
                    ->boolean()
                    ->trueIcon('heroicon-s-fire')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->sortable(),

                TextColumn::make('platform_filter')
                    ->label('Platform')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'instagram' => 'Instagram',
                        'tiktok' => 'TikTok',
                        'youtube' => 'YouTube',
                        'x' => 'X (Twitter)',
                        'pinterest' => 'Pinterest',
                        'facebook' => 'Facebook',
                        'linkedin' => 'LinkedIn',
                        'telegram' => 'Telegram',
                        'whatsapp' => 'WhatsApp',
                        default => '🌐 Tümü',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'instagram' => 'danger',
                        'tiktok' => 'gray',
                        'youtube' => 'danger',
                        'x' => 'info',
                        'pinterest' => 'danger',
                        'facebook' => 'info',
                        'linkedin' => 'info',
                        default => 'success',
                    })
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'trending' => 'warning',
                        'brand' => 'info',
                        'kids' => 'success',
                        'product' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('usage_count')
                    ->label('Kullanım')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->sortable(),

                TextColumn::make('last_used_at')
                    ->label('Son Kullanım')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Henüz kullanılmadı')
                    ->visibleFrom('lg')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Bitiş Tarihi')
                    ->dateTime('d.m.Y')
                    ->placeholder('Süresiz')
                    ->visibleFrom('xl')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label('Aktif'),

                ToggleColumn::make('is_blacklisted')
                    ->label('Kara Liste'),
            ])
            ->filters([
                SelectFilter::make('platform_filter')
                    ->label('Platform Filtresi')
                    ->options([
                        'instagram' => 'Instagram',
                        'tiktok' => 'TikTok',
                        'youtube' => 'YouTube',
                        'x' => 'X (Twitter)',
                        'pinterest' => 'Pinterest',
                        'facebook' => 'Facebook',
                        'linkedin' => 'LinkedIn',
                        'telegram' => 'Telegram',
                        'whatsapp' => 'WhatsApp',
                    ]),

                TernaryFilter::make('is_trending')
                    ->label('Trend Durumu')
                    ->placeholder('Tümü')
                    ->trueLabel('Sadece Revaçtakiler 🔥')
                    ->falseLabel('Standart Etiketler'),

                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options([
                        'trending' => 'Trend / Revaçta',
                        'brand' => 'Marka',
                        'product' => 'Ürün',
                        'kids' => 'Çocuk & Hediye',
                        'general' => 'Genel',
                        'campaign' => 'Kampanya',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Aktiflik Durumu'),
            ])
            ->headerActions([
                // 1. Toplu Revaçtaki Hashtag Ekle
                Action::make('bulk_add_trending')
                    ->label('Revaçtaki Hashtagleri Toplu Ekle')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
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

                // 2. Yapay Zeka ile Trend Keşif Sihirbazı
                Action::make('ai_trend_scout')
                    ->label('AI ile Revaçtaki Etiketleri Keşfet')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
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

                // 3. Hazır Platform Trend Paketlerini Yükle
                Action::make('load_preset_packs')
                    ->label('Hazır Trend Paketi Yükle')
                    ->icon('heroicon-o-bolt')
                    ->color('info')
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
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->iconButton()
                    ->tooltip('Düzenle')
                    ->modalWidth(\Filament\Support\Enums\MaxWidth::Large),

                Tables\Actions\DeleteAction::make()->iconButton()->tooltip('Sil'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    BulkAction::make('mark_trending')
                        ->label('Revaçta (Trend) Olarak İşaretle')
                        ->icon('heroicon-o-fire')
                        ->color('warning')
                        ->action(fn (Collection $records) => $records->each->update(['is_trending' => true, 'category' => 'trending', 'priority' => 9])),

                    BulkAction::make('unmark_trending')
                        ->label('Trend İşaretini Kaldır')
                        ->icon('heroicon-o-minus-circle')
                        ->action(fn (Collection $records) => $records->each->update(['is_trending' => false])),

                    BulkAction::make('blacklist')
                        ->label('Kara Listeye Al')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['is_blacklisted' => true])),

                    BulkAction::make('whitelist')
                        ->label('Kara Listeden Çıkar')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['is_blacklisted' => false])),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageHashtags::route('/'),
        ];
    }
}
