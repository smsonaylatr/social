<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VideoCategoryResource\Pages;
use App\Models\VideoCategory;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class VideoCategoryResource extends Resource
{
    protected static ?string $model = VideoCategory::class;
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Video Yönetimi';
    protected static ?string $navigationLabel = 'Video Kategorileri';
    protected static ?string $modelLabel = 'Kategori';
    protected static ?string $pluralModelLabel = 'Video Kategorileri';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('workspace_id')
                    ->label('Çalışma Alanı')
                    ->relationship('workspace', 'name')
                    ->default(1)
                    ->required(),

                Select::make('brand_id')
                    ->label('Marka')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),

                TextInput::make('name')
                    ->label('Kategori Adı')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),

                TextInput::make('slug')
                    ->label('Slug / URL')
                    ->required()
                    ->maxLength(255),

                ColorPicker::make('color')
                    ->label('Etiket Rengi')
                    ->default('#3b82f6'),

                Textarea::make('description')
                    ->label('Açıklama')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')
                    ->label('Renk')
                    ->width('40px'),

                TextColumn::make('name')
                    ->label('Kategori Adı')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->weight('bold'),

                TextColumn::make('brand.name')
                    ->label('Marka')
                    ->sortable()
                    ->visibleFrom('sm')
                    ->default('Genel'),

                TextColumn::make('videos_count')
                    ->counts('videos')
                    ->label('Video Sayısı')
                    ->badge()
                    ->color('primary')
                    ->visibleFrom('sm')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Oluşturulma')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->visibleFrom('md')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->iconButton()->tooltip('Düzenle'),
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
            'index' => Pages\ManageVideoCategories::route('/'),
        ];
    }
}
