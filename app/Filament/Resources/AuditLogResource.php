<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                    ->icon(fn ($record) => $record->user_id ? 'heroicon-m-user' : 'heroicon-m-cpu-chip')
                    ->weight('bold')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('action')
                    ->label('İşlem')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'video_uploaded' => 'Video Yüklendi',
                        'video_deleted' => 'Video Silindi',
                        'video_reanalyzed' => 'AI Yeniden Analiz',
                        'admin_login', 'user_login' => 'Yönetici Girişi',
                        'post_scheduled' => 'Gönderi Zamanlandı',
                        'post_published' => 'Gönderi Paylaşıldı',
                        'post_failed' => 'Paylaşım Başarısız',
                        'channel_connected' => 'Kanal Bağlandı',
                        'channel_disconnected' => 'Kanal Ayrıldı',
                        'system_init' => 'Sistem Kurulumu',
                        'system_deploy' => 'Sürüm Güncelleme',
                        default => str($state)->headline(),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'video_uploaded', 'post_published', 'channel_connected' => 'success',
                        'video_deleted', 'post_failed', 'channel_disconnected' => 'danger',
                        'video_reanalyzed', 'post_scheduled' => 'warning',
                        'admin_login', 'user_login' => 'info',
                        'system_deploy' => 'primary',
                        default => 'gray',
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'video_uploaded' => 'heroicon-m-arrow-up-tray',
                        'video_deleted' => 'heroicon-m-trash',
                        'video_reanalyzed' => 'heroicon-m-sparkles',
                        'admin_login', 'user_login' => 'heroicon-m-arrow-right-on-rectangle',
                        'post_scheduled' => 'heroicon-m-clock',
                        'post_published' => 'heroicon-m-check-badge',
                        'post_failed' => 'heroicon-m-x-circle',
                        'channel_connected' => 'heroicon-m-link',
                        'channel_disconnected' => 'heroicon-m-link-slash',
                        'system_deploy' => 'heroicon-m-arrow-path',
                        default => 'heroicon-m-information-circle',
                    })
                    ->searchable(),

                TextColumn::make('details')
                    ->label('Detay / Açıklama')
                    ->state(function (AuditLog $record): string {
                        $new = $record->new_values ?? [];
                        $old = $record->old_values ?? [];

                        return match ($record->action) {
                            'video_uploaded' => ($new['title'] ?? 'Video') . (isset($new['duration_seconds']) ? " ({$new['duration_seconds']}s)" : ''),
                            'video_deleted' => ($old['title'] ?? 'Video silindi'),
                            'video_reanalyzed' => $new['description'] ?? 'Teknik parametreler ve görsel güncellendi',
                            'admin_login', 'user_login' => 'Giriş yapan: ' . ($new['email'] ?? $record->user?->email ?? 'Yönetici'),
                            'post_scheduled' => 'Zaman: ' . ($new['scheduled_at'] ?? 'Planlandı'),
                            'post_published' => 'Sosyal medyada başarıyla paylaşıldı',
                            'post_failed' => 'Hata: ' . ($new['notes'] ?? 'Bilinmeyen hata'),
                            'channel_connected' => 'Kanal: ' . ($new['name'] ?? $new['username'] ?? 'Hesap bağlandı'),
                            'channel_disconnected' => 'Kanal bağlantısı kesildi',
                            'system_init' => 'Sistem ve ilk veritabanı kurulumu tamamlandı',
                            'system_deploy' => $new['details'] ?? 'Sürüm güncellemesi ve migrate tamamlandı',
                            default => ! empty($new) ? json_encode($new, JSON_UNESCAPED_UNICODE) : '-',
                        };
                    })
                    ->limit(45)
                    ->tooltip(function (AuditLog $record): ?string {
                        $new = $record->new_values ?? [];
                        return ! empty($new) ? json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : null;
                    })
                    ->wrap(),

                TextColumn::make('auditable_type')
                    ->label('Kaynak')
                    ->visibleFrom('sm')
                    ->formatStateUsing(function (?string $state, AuditLog $record): string {
                        if (empty($state)) {
                            return 'Genel Sistem';
                        }
                        $base = class_basename($state);
                        $label = match ($base) {
                            'Video' => 'Video',
                            'ScheduledPost' => 'Zamanlı Gönderi',
                            'SocialAccount' => 'Sosyal Hesap',
                            'User' => 'Kullanıcı',
                            'Brand' => 'Marka',
                            'Workspace' => 'Çalışma Alanı',
                            default => $base,
                        };
                        return $record->auditable_id ? "{$label} #{$record->auditable_id}" : $label;
                    }),

                TextColumn::make('ip_address')
                    ->label('IP Adresi')
                    ->fontFamily('mono')
                    ->visibleFrom('lg')
                    ->default('127.0.0.1'),

                TextColumn::make('created_at')
                    ->label('İşlem Tarihi')
                    ->dateTime('d.m.Y H:i:s')
                    ->visibleFrom('md')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('İşlem Türü')
                    ->options([
                        'video_uploaded' => 'Video Yüklendi',
                        'video_reanalyzed' => 'AI Yeniden Analiz',
                        'video_deleted' => 'Video Silindi',
                        'admin_login' => 'Yönetici Girişi',
                        'post_scheduled' => 'Gönderi Zamanlandı',
                        'post_published' => 'Gönderi Paylaşıldı',
                        'channel_connected' => 'Kanal Bağlandı',
                        'system_deploy' => 'Sürüm Güncelleme',
                        'system_init' => 'Sistem Kurulumu',
                    ]),
            ])
            ->actions([
                ViewAction::make()
                    ->label('Detay')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('İşlem ve Denetim Kaydı Detayı'),
            ])
            ->emptyStateHeading('Henüz İşlem ve Denetim Kaydı Bulunmuyor')
            ->emptyStateDescription('Sistemdeki tüm video yüklemeleri, AI analizleri, yayın hareketleri ve yönetici oturumları otomatik olarak burada arşivlenir.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('İşlem Özeti')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('İşlemi Yapan')
                            ->default('Sistem / Otomasyon'),

                        TextEntry::make('action')
                            ->label('İşlem Türü')
                            ->badge(),

                        TextEntry::make('ip_address')
                            ->label('IP Adresi')
                            ->fontFamily('mono'),

                        TextEntry::make('auditable_type')
                            ->label('İlgili Model / Kaynak')
                            ->formatStateUsing(fn (?string $state) => class_basename($state ?? 'Genel Sistem')),

                        TextEntry::make('auditable_id')
                            ->label('Kaynak Kayıt ID')
                            ->default('-'),

                        TextEntry::make('created_at')
                            ->label('İşlem Tarihi')
                            ->dateTime('d.m.Y H:i:s'),
                    ]),

                Section::make('Veri Değişiklikleri')
                    ->schema([
                        TextEntry::make('new_values')
                            ->label('Yeni / Uygulanan Değerler (JSON)')
                            ->formatStateUsing(fn ($state) => empty($state) ? 'Değer yok' : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                            ->fontFamily('mono')
                            ->columnSpanFull(),

                        TextEntry::make('old_values')
                            ->label('Önceki Değerler (JSON)')
                            ->formatStateUsing(fn ($state) => empty($state) ? 'Değer yok' : json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAuditLogs::route('/'),
        ];
    }
}
