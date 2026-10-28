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
                        ->mutateDehydratedStateUsing(fn ($state) => preg_replace('/[^\p{L}\p{N}_]/u', '', str_replace(' ', '', ltrim(trim($state), '#')))),

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
                    ->label('Kara Liste')
                    ->visibleFrom('sm'),
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
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns([
                'default' => 1,
                'sm' => 2,
                'lg' => 4,
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
                ])
                ->label('Seçilenlerle İşlem Yap')
                ->icon('heroicon-o-check-badge'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageHashtags::route('/'),
        ];
    }
}
