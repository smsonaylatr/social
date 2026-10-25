<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HashtagResource\Pages;
use App\Models\Hashtag;
use App\Models\HashtagGroup;
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
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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
                TextInput::make('tag')
                    ->label('Hashtag')
                    ->placeholder('Örn: patenliayakkabi')
                    ->required()
                    ->maxLength(255)
                    ->mutateDehydratedStateUsing(fn ($state) => ltrim($state, '#')),

                Select::make('category')
                    ->label('Kategori')
                    ->options([
                        'brand' => 'Marka',
                        'product' => 'Ürün',
                        'general' => 'Genel',
                        'trending' => 'Trend',
                        'kids' => 'Çocuk',
                        'gift' => 'Hediye',
                        'education' => 'Eğitim & İpuçları',
                        'campaign' => 'Kampanya',
                        'seasonal' => 'Sezonluk',
                    ])
                    ->default('general')
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
                        'linkedin' => 'LinkedIn',
                        'facebook' => 'Facebook',
                    ])
                    ->placeholder('Tüm Platformlarda Geçerli'),

                TextInput::make('priority')
                    ->label('Öncelik Puanı (1-10)')
                    ->numeric()
                    ->default(5)
                    ->required(),

                Toggle::make('is_blacklisted')
                    ->label('Kara Listeye Al (Yayınlarda Engelle)')
                    ->default(false),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tag')
                    ->label('Hashtag')
                    ->formatStateUsing(fn (string $state): string => '#' . ltrim($state, '#'))
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('bold'),

                TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->color('primary')
                    ->visibleFrom('sm')
                    ->sortable(),

                TextColumn::make('platform_filter')
                    ->label('Platform')
                    ->badge()
                    ->color('info')
                    ->visibleFrom('md')
                    ->default('Tümü'),

                TextColumn::make('priority')
                    ->label('Öncelik')
                    ->visibleFrom('lg')
                    ->sortable(),

                TextColumn::make('usage_count')
                    ->label('Kullanım')
                    ->badge()
                    ->visibleFrom('lg')
                    ->sortable(),

                TextColumn::make('last_used_at')
                    ->label('Son Kullanım')
                    ->dateTime('d.m.Y H:i')
                    ->visibleFrom('xl')
                    ->sortable(),

                ToggleColumn::make('is_blacklisted')
                    ->label('Kara Liste')
                    ->visibleFrom('md'),

                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options([
                        'brand' => 'Marka',
                        'product' => 'Ürün',
                        'kids' => 'Çocuk',
                        'general' => 'Genel',
                        'trending' => 'Trend',
                    ]),
            ])
            ->headerActions([
                Action::make('bulk_add')
                    ->label('Toplu Hashtag Ekle')
                    ->icon('heroicon-o-plus-circle')
                    ->form([
                        Textarea::make('tags_text')
                            ->label('Hashtag Listesi')
                            ->placeholder("Boşluk veya virgül ile ayırarak yazabilirsiniz:\n#paten #patenliayakkabi #cocukmodasi #rollerkids")
                            ->rows(5)
                            ->required(),

                        Select::make('category')
                            ->label('Kategori')
                            ->options([
                                'brand' => 'Marka',
                                'product' => 'Ürün',
                                'kids' => 'Çocuk',
                                'general' => 'Genel',
                                'trending' => 'Trend',
                            ])
                            ->default('general')
                            ->required(),

                        TextInput::make('priority')
                            ->label('Varsayılan Öncelik')
                            ->numeric()
                            ->default(5)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $rawTags = preg_split('/[\s,]+/', $data['tags_text']);
                        $addedCount = 0;

                        foreach ($rawTags as $raw) {
                            $tag = ltrim(trim($raw), '#');
                            if (empty($tag)) {
                                continue;
                            }

                            Hashtag::firstOrCreate(
                                ['workspace_id' => 1, 'tag' => $tag],
                                [
                                    'category' => $data['category'],
                                    'priority' => $data['priority'],
                                    'is_active' => true,
                                ]
                            );
                            $addedCount++;
                        }

                        Notification::make()
                            ->title("{$addedCount} adet hashtag havuza eklendi")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->iconButton()->tooltip('Düzenle'),
                Tables\Actions\DeleteAction::make()->iconButton()->tooltip('Sil'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
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
