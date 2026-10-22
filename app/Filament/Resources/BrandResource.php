<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandResource\Pages;
use App\Models\Brand;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Sistem Ayarları';
    protected static ?string $navigationLabel = 'Marka Profili';
    protected static ?string $modelLabel = 'Marka Profili';
    protected static ?string $pluralModelLabel = 'Marka Profilleri';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Marka Temel Bilgileri')
                    ->schema([
                        TextInput::make('name')
                            ->label('Marka Adı')
                            ->placeholder('Patenli Ayakkabılar')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('website')
                            ->label('Web Sitesi')
                            ->placeholder('https://patenliayakkabilar.com')
                            ->url()
                            ->required(),

                        TextInput::make('slogan')
                            ->label('Marka Sloganı')
                            ->placeholder('Walk. Roll. Fly.')
                            ->required(),

                        Select::make('brand_tone')
                            ->label('İçerik Dili / Tonu')
                            ->options([
                                'energetic' => 'Dinamik, Enerjik & İlham Verici',
                                'playful' => 'Eğlenceli & Çocuk/Genç Dostu',
                                'professional' => 'Kurumsal & Güvenilir',
                                'casual' => 'Samimi & Günlük',
                            ])
                            ->default('energetic')
                            ->required(),

                        TextInput::make('target_audience')
                            ->label('Hedef Kitle')
                            ->placeholder('Çocuklar, gençler, ebeveynler, hediye arayanlar')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('AI İçerik ve SEO Kuralları')
                    ->schema([
                        TagsInput::make('seo_keywords')
                            ->label('Öncelikli SEO Kelimeleri')
                            ->placeholder('Kelime yazıp enter\'a basın')
                            ->columnSpanFull(),

                        TagsInput::make('forbidden_words')
                            ->label('Yasaklı / Kullanılmayacak Kelimeler')
                            ->placeholder('Kullanılması istenmeyen kelimeler')
                            ->columnSpanFull(),

                        Textarea::make('default_cta')
                            ->label('Varsayılan Call To Action (CTA)')
                            ->rows(2)
                            ->columnSpanFull(),

                        KeyValue::make('landing_urls')
                            ->label('Özel Sayfa Bağlantıları (Landing URLs)')
                            ->keyLabel('Kategori / Sayfa Adı')
                            ->valueLabel('URL Adresi')
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Marka Adı')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('website')
                    ->label('Web Sitesi')
                    ->url(fn ($record) => $record->website, shouldOpenInNewTab: true),

                TextColumn::make('slogan')
                    ->label('Slogan')
                    ->extraAttributes(['class' => 'italic']),

                TextColumn::make('brand_tone')
                    ->label('Ton')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('videos_count')
                    ->counts('videos')
                    ->label('Video')
                    ->badge(),

                ToggleColumn::make('is_active')
                    ->label('Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageBrands::route('/'),
        ];
    }
}
