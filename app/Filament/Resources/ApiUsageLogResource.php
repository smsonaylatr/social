<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiUsageLogResource\Pages;
use App\Models\ApiUsageLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ApiUsageLogResource extends Resource
{
    protected static ?string $model = ApiUsageLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?string $navigationGroup = 'Raporlar ve Günlükler';
    protected static ?string $navigationLabel = 'API İstek ve Maliyet Kayıtları';
    protected static ?string $modelLabel = 'API Günlüğü';
    protected static ?string $pluralModelLabel = 'API İstek ve Maliyet Günlükleri';
    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('provider')
                    ->label('Sağlayıcı')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'openai' => 'success',
                        'meta' => 'info',
                        'google', 'youtube' => 'danger',
                        'tiktok' => 'primary',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('endpoint')
                    ->label('Uç Nokta (Endpoint)')
                    ->fontFamily('mono')
                    ->limit(35),

                TextColumn::make('status_code')
                    ->label('HTTP')
                    ->badge()
                    ->color(fn (?int $state): string => $state >= 200 && $state < 300 ? 'success' : 'danger'),

                TextColumn::make('response_time_ms')
                    ->label('Süre')
                    ->suffix(' ms')
                    ->sortable(),

                TextColumn::make('prompt_tokens')
                    ->label('Girdi Token')
                    ->numeric()
                    ->toggleable(),

                TextColumn::make('completion_tokens')
                    ->label('Çıktı Token')
                    ->numeric()
                    ->toggleable(),

                TextColumn::make('estimated_cost_usd')
                    ->label('Maliyet')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 5))
                    ->weight('bold')
                    ->sortable(),

                IconColumn::make('is_error')
                    ->label('Hata')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Zaman')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('provider')
                    ->options([
                        'openai' => 'OpenAI',
                        'meta' => 'Meta (Facebook / Instagram)',
                        'google' => 'Google / YouTube',
                        'x' => 'X (Twitter)',
                        'pinterest' => 'Pinterest',
                        'tiktok' => 'TikTok',
                        'linkedin' => 'LinkedIn',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageApiUsageLogs::route('/'),
        ];
    }
}
