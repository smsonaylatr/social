<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KeywordLibraryResource\Pages;
use App\Models\KeywordLibrary;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KeywordLibraryResource extends Resource
{
    protected static ?string $model = KeywordLibrary::class;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';
    protected static ?string $navigationGroup = 'AI ve SEO Stüdyosu';
    protected static ?string $navigationLabel = 'SEO ve Anahtar Kelimeler';
    protected static ?string $modelLabel = 'Anahtar Kelime';
    protected static ?string $pluralModelLabel = 'SEO ve Anahtar Kelimeler';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('keyword')
                    ->label('Anahtar Kelime')
                    ->placeholder('Örn: çocuk patenli ayakkabı')
                    ->required()
                    ->maxLength(255),

                Select::make('search_intent')
                    ->label('Arama Niyeti')
                    ->options([
                        'transactional' => 'Satın Alma / Dönüşüm (Transactional)',
                        'commercial' => 'Ürün Araştırması (Commercial)',
                        'informational' => 'Bilgi Arama (Informational)',
                        'navigational' => 'Marka / Doğrudan (Navigational)',
                    ])
                    ->default('commercial')
                    ->required(),

                Select::make('category')
                    ->label('Kategori')
                    ->options([
                        'product' => 'Ürün Odaklı',
                        'brand' => 'Marka Odaklı',
                        'kids' => 'Çocuk & Aile',
                        'sport' => 'Spor & Aktivite',
                    ])
                    ->default('product')
                    ->required(),

                TextInput::make('priority')
                    ->label('Öncelik Puanı (1-10)')
                    ->numeric()
                    ->default(5)
                    ->required(),

                TextInput::make('target_url')
                    ->label('Hedef Sayfa / URL')
                    ->placeholder('https://patenliayakkabilar.com/kategori')
                    ->url(),

                TextInput::make('language')
                    ->label('Dil')
                    ->default('tr')
                    ->maxLength(5)
                    ->required(),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')
                    ->label('Anahtar Kelime')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('bold'),

                TextColumn::make('search_intent')
                    ->label('Arama Niyeti')
                    ->badge()
                    ->visibleFrom('sm')
                    ->color(fn (string $state): string => match ($state) {
                        'transactional' => 'success',
                        'commercial' => 'primary',
                        'informational' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('priority')
                    ->label('Öncelik')
                    ->visibleFrom('md')
                    ->sortable(),

                TextColumn::make('usage_count')
                    ->label('Kullanım')
                    ->badge()
                    ->visibleFrom('lg')
                    ->sortable(),

                TextColumn::make('last_used_at')
                    ->label('Son Kullanım')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('Hiç kullanılmadı')
                    ->visibleFrom('md')
                    ->sortable(),

                TextColumn::make('target_url')
                    ->label('Hedef URL')
                    ->limit(30)
                    ->visibleFrom('lg')
                    ->url(fn ($record) => $record->target_url, shouldOpenInNewTab: true),

                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->filters([
                SelectFilter::make('search_intent')
                    ->options([
                        'transactional' => 'Satın Alma',
                        'commercial' => 'Ticari İnceleme',
                        'informational' => 'Bilgilendirici',
                    ]),
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
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageKeywordLibraries::route('/'),
        ];
    }
}
