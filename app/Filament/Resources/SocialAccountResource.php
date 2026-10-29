<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SocialAccountResource\Pages;
use App\Models\SocialAccount;
use App\Models\SocialAccountCredential;
use App\Models\SocialPlatform;
use App\Services\Social\SocialPublisherManager;
use App\Support\SocialPlatformSetupGuide;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class SocialAccountResource extends Resource
{
    protected static ?string $model = SocialAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $navigationGroup = 'Sosyal Medya';
    protected static ?string $navigationLabel = 'Hesaplar & Yetkilendirme';
    protected static ?string $modelLabel = 'Sosyal Medya Hesabı';
    protected static ?string $pluralModelLabel = 'Sosyal Medya Hesapları';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Hesap ve Platform Bilgileri')
                    ->schema([
                        Select::make('platform_id')
                            ->label('Platform')
                            ->relationship('platform', 'name')
                            ->live()
                            ->required(),

                        TextInput::make('name')
                            ->label('Hesap / Sayfa / Kanal Adı')
                            ->placeholder('Örn: Resmi İşletme Sayfası')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('username')
                            ->label('Kullanıcı Adı (@)')
                            ->placeholder('kullaniciadi')
                            ->maxLength(255),

                        TextInput::make('account_id')
                            ->label('Harici Hesap / Sayfa / Kanal Kimliği (ID)')
                            ->placeholder('Boş bırakırsanız Access Token ile otomatik bulunur (Örn: 178414...)')
                            ->helperText(function (Get $get): string {
                                $platformId = $get('platform_id');
                                $platform = $platformId ? SocialPlatform::find($platformId) : null;
                                $code = $platform?->code instanceof \BackedEnum ? $platform->code->value : (string) ($platform?->code ?? 'instagram');
                                return (SocialPlatformSetupGuide::get($code)['id_field_hint'] ?? '') . ' (Access Token girdiğinizde sistem gerçek Hesap/Sayfa ID\'sini otomatik algılar.)';
                            })
                            ->maxLength(255),

                        Select::make('account_type')
                            ->label('Hesap Türü')
                            ->options([
                                'business' => 'Instagram / TikTok / WhatsApp İşletme (Business)',
                                'page' => 'Facebook / LinkedIn Sayfası',
                                'channel' => 'YouTube / Telegram Kanalı',
                                'profile' => 'X / Pinterest / İçerik Üretici Profili',
                            ])
                            ->default('business')
                            ->required(),
                    ])->columns(2),

                Section::make('Erişim Kodu (Token) Alma Linkleri & Kurulum Kılavuzu')
                    ->description('Seçtiğiniz platformun resmi erişim kodunu (Access Token) almak için aşağıdaki doğrudan bağlantıları ve adım adım kılavuzu kullanın.')
                    ->schema([
                        Placeholder::make('platform_setup_guide')
                            ->hiddenLabel()
                            ->content(function (Get $get): HtmlString {
                                $platformId = $get('platform_id');
                                $platform = $platformId ? SocialPlatform::find($platformId) : SocialPlatform::first();
                                $code = $platform?->code instanceof \BackedEnum ? $platform->code->value : (string) ($platform?->code ?? 'instagram');
                                $guide = SocialPlatformSetupGuide::get($code);

                                $scopesBadges = collect($guide['scopes'])->map(function ($s) {
                                    return '<code style="display:inline-block; padding:2px 7px; margin:2px; border-radius:5px; background:rgba(59,130,246,0.14); border:1px solid rgba(59,130,246,0.3); color:#93c5fd; font-size:11px; font-family:monospace;">' . e($s) . '</code>';
                                })->implode(' ');

                                $stepsHtml = collect($guide['steps'])->map(function ($step) {
                                    return '<li style="padding:5px 0; color:#d4d4d8; font-size:12.5px; line-height:1.5; border-bottom:1px dashed rgba(255,255,255,0.07);">' . e($step) . '</li>';
                                })->implode('');

                                $html = '
                                <div style="border-radius:12px; border:1px solid rgba(255,255,255,0.12); background:#111318; padding:16px; color:#f4f4f5;">
                                    <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid rgba(255,255,255,0.08);">
                                        <div>
                                            <div style="font-size:14px; font-weight:800; color:#ffffff;">' . e($guide['name']) . ' — Erişim Kodu & API Kurulum Rehberi</div>
                                            <div style="font-size:11.5px; color:#a1a1aa; margin-top:2px;">' . e($guide['api_name']) . '</div>
                                        </div>
                                        <span style="padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; background:rgba(16,185,129,0.15); color:#34d399; border:1px solid rgba(16,185,129,0.3);">Resmi OAuth 2.0 / Token</span>
                                    </div>

                                    <!-- Direct Action Links -->
                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:10px; margin-bottom:14px;">
                                        <a href="' . e($guide['token_generator_url']) . '" target="_blank" rel="noopener noreferrer"
                                           style="display:flex; align-items:center; justify-content:space-between; padding:10px 13px; border-radius:9px; background:linear-gradient(135deg, #2563eb, #1d4ed8); color:#ffffff; font-size:12px; font-weight:700; text-decoration:none; box-shadow:0 3px 10px rgba(37,99,235,0.28);">
                                            <span>🔑 ' . e($guide['token_generator_label']) . '</span>
                                            <span style="font-size:14px; margin-left:6px;">↗</span>
                                        </a>

                                        <a href="' . e($guide['oauth_url']) . '"
                                           style="display:flex; align-items:center; justify-content:space-between; padding:10px 13px; border-radius:9px; background:rgba(16,185,129,0.16); border:1px solid rgba(16,185,129,0.4); color:#34d399; font-size:12px; font-weight:700; text-decoration:none;">
                                            <span>⚡ Otomatik OAuth 2.0 Erişim İzni Al</span>
                                            <span style="font-size:14px; margin-left:6px;">→</span>
                                        </a>

                                        <a href="' . e($guide['developer_portal_url']) . '" target="_blank" rel="noopener noreferrer"
                                           style="display:flex; align-items:center; justify-content:space-between; padding:10px 13px; border-radius:9px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.14); color:#e4e4e7; font-size:12px; font-weight:600; text-decoration:none;">
                                            <span>🛠️ ' . e($guide['developer_portal_label']) . '</span>
                                            <span style="font-size:14px; margin-left:6px;">↗</span>
                                        </a>
                                    </div>

                                    <!-- Redirect URI & Required Scopes -->
                                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:10px; margin-bottom:14px; padding:10px 12px; border-radius:8px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.07);">
                                        <div>
                                            <div style="font-size:10.5px; font-weight:700; text-transform:uppercase; color:#9ca3af; margin-bottom:4px;">OAuth Redirect URI (Uygulamaya Eklenecek Adres)</div>
                                            <code style="display:block; padding:5px 8px; border-radius:6px; background:#090a0f; color:#38bdf8; font-size:11.5px; font-family:monospace; user-select:all;">' . e($guide['redirect_uri']) . '</code>
                                        </div>
                                        <div>
                                            <div style="font-size:10.5px; font-weight:700; text-transform:uppercase; color:#9ca3af; margin-bottom:4px;">Seçilmesi Gereken İzinler (Scopes)</div>
                                            <div>' . $scopesBadges . '</div>
                                        </div>
                                    </div>

                                    <!-- Step-by-Step Setup Instructions -->
                                    <div>
                                        <div style="font-size:12px; font-weight:700; color:#fbbf24; margin-bottom:6px;">📋 Adım Adım ' . e($guide['name']) . ' Erişim Kodu Alma ve Bağlama Kılavuzu:</div>
                                        <ul style="list-style:none; margin:0; padding:0;">' . $stepsHtml . '</ul>
                                    </div>
                                </div>';

                                return new HtmlString($html);
                            }),
                    ]),

                Section::make('OAuth Kimlik Bilgileri (Güvenli Şifrelenmiş)')
                    ->description('Yukarıdaki bağlantıdan aldığınız Erişim Anahtarını (Access Token) buraya yapıştırın. Tüm anahtarlar AES-256 ile şifrelenerek saklanır.')
                    ->schema([
                        TextInput::make('access_token')
                            ->label('Erişim Anahtarı (Access Token)')
                            ->autocomplete(false)
                            ->extraInputAttributes([
                                'data-1p-ignore' => 'true',
                                'data-lpignore' => 'true',
                                'data-form-type' => 'other',
                                'spellcheck' => 'false',
                            ])
                            ->required(fn (string $context): bool => $context === 'create')
                            ->helperText('Resmi OAuth veya Geliştirici Portalından alınan kalıcı erişim anahtarı'),

                        TextInput::make('refresh_token')
                            ->label('Yenileme Anahtarı (Refresh Token)')
                            ->autocomplete(false)
                            ->extraInputAttributes([
                                'data-1p-ignore' => 'true',
                                'data-lpignore' => 'true',
                                'data-form-type' => 'other',
                                'spellcheck' => 'false',
                            ])
                            ->helperText('Destekleyen platformlarda (Google, X, TikTok, Pinterest) otomatik token yenileme için'),

                        DateTimePicker::make('token_expires_at')
                            ->label('Token Bitiş Tarihi'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['platform']))
            ->columns([
                TextColumn::make('platform.name')
                    ->label('Platform')
                    ->badge()
                    ->color(fn ($record) => match ($record->platform?->code?->value ?? $record->platform?->code) {
                        'facebook' => 'info',
                        'instagram' => 'warning',
                        'youtube' => 'danger',
                        'tiktok' => 'primary',
                        'x' => 'gray',
                        'whatsapp' => 'success',
                        default => 'secondary',
                    })
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Hesap Adı')
                    ->searchable()
                    ->wrap()
                    ->weight('bold')
                    ->description(fn (SocialAccount $record): string => '@' . ($record->username ?: $record->account_id)),

                TextColumn::make('account_type')
                    ->label('Tür')
                    ->badge()
                    ->visibleFrom('md')
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
                    ->visibleFrom('lg')
                    ->sortable()
                    ->placeholder('Henüz paylaşılmadı'),
            ])
            ->headerActions([
                Action::make('channels_view')
                    ->label('Platform Kanalları')
                    ->icon('heroicon-o-squares-2x2')
                    ->color('primary')
                    ->url(fn (): string => url('/admin/social-channels')),

                Action::make('developer_guide')
                    ->label('API Kılavuzu')
                    ->icon('heroicon-o-book-open')
                    ->color('info')
                    ->modalWidth('5xl')
                    ->modalHeading('Tüm Platformlar İçin Erişim Kodu Alma Linkleri & Adım Adım Kurulum Kılavuzu')
                    ->modalContent(view('filament.modals.developer-api-guide')),
            ])
            ->actions([
                Action::make('get_token_link')
                    ->label('Erişim Kodu Al')
                    ->icon('heroicon-o-key')
                    ->iconButton()
                    ->tooltip('Resmi Erişim Kodu Al')
                    ->color('info')
                    ->url(function (SocialAccount $record): string {
                        $code = $record->platform?->code instanceof \BackedEnum
                            ? $record->platform->code->value
                            : (string) ($record->platform?->code ?? 'instagram');
                        return SocialPlatformSetupGuide::get($code)['token_generator_url'];
                    })
                    ->openUrlInNewTab(),

                Action::make('test_connection')
                    ->label('Bağlantıyı Doğrula')
                    ->icon('heroicon-o-check-circle')
                    ->iconButton()
                    ->tooltip('Bağlantıyı Doğrula')
                    ->color('success')
                    ->action(function (SocialAccount $record) {
                        $manager = app(SocialPublisherManager::class);
                        try {
                            $code = $record->platform->code instanceof \BackedEnum
                                ? $record->platform->code->value
                                : (string) $record->platform->code;
                            $publisher = $manager->getPublisher($code);
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

                Tables\Actions\EditAction::make()
                    ->mutateRecordDataUsing(function (array $data, SocialAccount $record): array {
                        $credential = $record->credential;
                        if ($credential) {
                            $data['access_token'] = $credential->access_token;
                            $data['refresh_token'] = $credential->refresh_token;
                            $data['token_expires_at'] = $credential->token_expires_at;
                        }
                        return $data;
                    })
                    ->using(function (Model $record, array $data): Model {
                        $accessToken = $data['access_token'] ?? null;
                        $refreshToken = $data['refresh_token'] ?? null;
                        $tokenExpiresAt = $data['token_expires_at'] ?? null;

                        unset($data['access_token'], $data['refresh_token'], $data['token_expires_at']);

                        $record->update($data);

                        if (! empty($accessToken)) {
                            SocialAccountCredential::updateOrCreate(
                                ['social_account_id' => $record->id],
                                [
                                    'access_token' => $accessToken,
                                    'refresh_token' => $refreshToken,
                                    'token_expires_at' => $tokenExpiresAt,
                                ]
                            );

                            // Auto-resolve real Instagram Business ID or Facebook Page ID from token
                            try {
                                $code = $record->platform?->code instanceof \BackedEnum
                                    ? $record->platform->code->value
                                    : (string) $record->platform?->code;
                                $publisher = app(SocialPublisherManager::class)->getPublisher($code);
                                if (method_exists($publisher, 'resolveInstagramAccount')) {
                                    $publisher->resolveInstagramAccount($record, $accessToken, true);
                                } elseif (method_exists($publisher, 'resolveFacebookPage')) {
                                    $publisher->resolveFacebookPage($record, $accessToken);
                                }
                            } catch (\Throwable) {
                                // Ignore resolution error during save
                            }
                        }

                        return $record;
                    }),

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
