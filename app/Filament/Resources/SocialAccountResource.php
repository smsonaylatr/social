<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SocialAccountResource\Pages;
use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Services\Social\SocialPublisherManager;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SocialAccountResource extends Resource
{
    protected static ?string $model = SocialAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationGroup = 'Sosyal Medya';
    protected static ?string $navigationLabel = 'Hesaplar & Yetkilendirme';
    protected static ?string $modelLabel = 'Sosyal Medya Hesabı';
    protected static ?string $pluralModelLabel = 'Sosyal Medya Hesapları';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Hesap ve Platform Bilgileri')
                    ->schema([
                        Select::make('platform_id')
                            ->label('Platform')
                            ->relationship('platform', 'name')
                            ->required(),

                        Select::make('brand_id')
                            ->label('Marka')
                            ->relationship('brand', 'name')
                            ->default(1)
                            ->required(),

                        TextInput::make('name')
                            ->label('Hesap / Sayfa / Kanal Adı')
                            ->placeholder('Örn: Patenli Ayakkabılar Resmi Sayfası')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('username')
                            ->label('Kullanıcı Adı (@)')
                            ->placeholder('patenliayakkabilar')
                            ->maxLength(255),

                        TextInput::make('account_id')
                            ->label('Harici Hesap / Sayfa / Kanal Kimliği (ID)')
                            ->placeholder('Örn: 109283746524321')
                            ->required()
                            ->maxLength(255),

                        Select::make('account_type')
                            ->label('Hesap Türü')
                            ->options([
                                'page' => 'Facebook / LinkedIn Sayfası',
                                'business' => 'Instagram Professional / Business',
                                'channel' => 'YouTube Kanalı',
                                'profile' => 'X / TikTok / Pinterest Profili',
                            ])
                            ->default('page')
                            ->required(),
                    ])->columns(2),

                Section::make('OAuth Kimlik Bilgileri (Güvenli Şifrelenmiş)')
                    ->description('Tüm erişim anahtarları AES-256 algoritmasıyla şifrelenerek veritabanında saklanır.')
                    ->schema([
                        TextInput::make('access_token')
                            ->label('Erişim Anahtarı (Access Token)')
                            ->password()
                            ->revealable()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->helperText('Resmi OAuth veya Geliştirici Portalından alınan kalıcı erişim anahtarı'),

                        TextInput::make('refresh_token')
                            ->label('Yenileme Anahtarı (Refresh Token)')
                            ->password()
                            ->revealable()
                            ->helperText('Destekleyen platformlarda (Google, X, TikTok) otomatik token yenileme için'),

                        DateTimePicker::make('token_expires_at')
                            ->label('Token Bitiş Tarihi'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('platform.name')
                    ->label('Platform')
                    ->badge()
                    ->color(fn ($record) => match ($record->platform?->code?->value) {
                        'facebook' => 'info',
                        'instagram' => 'warning',
                        'youtube' => 'danger',
                        'tiktok' => 'primary',
                        'x' => 'gray',
                        default => 'secondary',
                    })
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Hesap Adı')
                    ->searchable()
                    ->description(fn (SocialAccount $record): string => '@' . ($record->username ?: $record->account_id)),

                TextColumn::make('account_type')
                    ->label('Tür')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'expired' => 'warning',
                        'revoked', 'rate_limited' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Bağlı (Aktif)',
                        'expired' => 'Süresi Dolmuş',
                        'revoked' => 'Yetki Kaldırılmış',
                        'rate_limited' => 'Kota Sınırında',
                        default => $state,
                    }),

                TextColumn::make('last_posted_at')
                    ->label('Son Gönderi')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->placeholder('Henüz paylaşılmadı'),
            ])
            ->headerActions([
                Action::make('developer_guide')
                    ->label('API Kurulum Sihirbazı & Scope Rehberi')
                    ->icon('heroicon-o-book-open')
                    ->color('info')
                    ->modalHeading('Geliştirici Portalları ve İzin Gereksinimleri')
                    ->modalContent(view('filament.modals.developer-api-guide')),
            ])
            ->actions([
                Action::make('test_connection')
                    ->label('Bağlantıyı Doğrula')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (SocialAccount $record) {
                        $manager = app(SocialPublisherManager::class);
                        try {
                            $publisher = $manager->getPublisher($record->platform->code->value);
                            $isValid = $publisher->validateConnection($record);

                            if ($isValid) {
                                $record->update(['last_synced_at' => now(), 'status' => 'active']);
                                Notification::make()
                                    ->title("{$record->platform->name} Bağlantısı Doğrulandı")
                                    ->body("Hesap kimliği ({$record->account_id}) ve yetki izinleri geçerli.")
                                    ->success()
                                    ->send();
                            } else {
                                $record->update(['status' => 'expired']);
                                Notification::make()
                                    ->title('Bağlantı Hatası')
                                    ->body('Erişim anahtarı geçersiz veya süresi dolmuş. Lütfen token yenileyin.')
                                    ->warning()
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Doğrulama Hatası')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

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
            'index' => Pages\ManageSocialAccounts::route('/'),
        ];
    }
}
