<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Raporlar ve Günlükler';
    protected static ?string $navigationLabel = 'İşlem ve Denetim Kayıtları';
    protected static ?string $modelLabel = 'Denetim Kaydı';
    protected static ?string $pluralModelLabel = 'İşlem ve Denetim Kayıtları';
    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Kullanıcı')
                    ->default('Sistem / Otomasyon')
                    ->sortable(),

                TextColumn::make('action')
                    ->label('İşlem')
                    ->badge()
                    ->color('primary')
                    ->searchable(),

                TextColumn::make('auditable_type')
                    ->label('Kaynak')
                    ->formatStateUsing(fn (?string $state) => class_basename($state ?? 'Genel')),

                TextColumn::make('ip_address')
                    ->label('IP Adresi')
                    ->fontFamily('mono')
                    ->default('127.0.0.1'),

                TextColumn::make('created_at')
                    ->label('İşlem Tarihi')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAuditLogs::route('/'),
        ];
    }
}
