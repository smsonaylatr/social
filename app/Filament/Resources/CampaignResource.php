<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Models\Campaign;
use App\Models\Playlist;
use App\Models\SocialAccount;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationGroup = 'Yayın ve Otomasyon';
    protected static ?string $navigationLabel = 'Kampanyalar';
    protected static ?string $modelLabel = 'Kampanya';
    protected static ?string $pluralModelLabel = 'Kampanyalar';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Kampanya Detayları')
                    ->schema([
                        TextInput::make('name')
                            ->label('Kampanya Adı')
                            ->placeholder('Örn: Yeni Sezon Patenli Ayakkabı Tanıtımı')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255),

                        Select::make('brand_id')
                            ->label('Marka')
                            ->relationship('brand', 'name')
                            ->default(1)
                            ->required(),

                        Select::make('playlist_id')
                            ->label('Bağlı Video Oynatma Listesi')
                            ->options(Playlist::pluck('name', 'id'))
                            ->searchable(),

                        DateTimePicker::make('start_date')
                            ->label('Başlangıç Tarihi')
                            ->default(now()),

                        DateTimePicker::make('end_date')
                            ->label('Bitiş Tarihi')
                            ->default(now()->addMonths(1)),

                        Select::make('status')
                            ->label('Durum')
                            ->options([
                                'draft' => 'Taslak',
                                'active' => 'Aktif',
                                'paused' => 'Duraklatıldı',
                                'completed' => 'Tamamlandı',
                            ])
                            ->default('active')
                            ->required(),

                        TextInput::make('target_url')
                            ->label('Kampanya Hedef URL')
                            ->placeholder('https://patenliayakkabilar.com/yeni-sezon')
                            ->url(),

                        TextInput::make('utm_campaign')
                            ->label('UTM Kampanya Kodu')
                            ->placeholder('yeni_sezon_2026'),

                        Textarea::make('description')
                            ->label('Kampanya Notları & Hedefleri')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Kampanya')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Campaign $record): string => $record->brand?->name ?? 'Varsayılan'),

                TextColumn::make('playlist.name')
                    ->label('Video Listesi')
                    ->badge()
                    ->color('primary')
                    ->default('Liste Seçilmedi'),

                TextColumn::make('start_date')
                    ->label('Başlangıç')
                    ->dateTime('d.m.Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Bitiş')
                    ->dateTime('d.m.Y')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'paused' => 'warning',
                        'completed' => 'gray',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Aktif',
                        'paused' => 'Duraklatıldı',
                        'completed' => 'Tamamlandı',
                        default => 'Taslak',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Aktif',
                        'paused' => 'Duraklatıldı',
                        'completed' => 'Tamamlandı',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ManageCampaigns::route('/'),
        ];
    }
}
