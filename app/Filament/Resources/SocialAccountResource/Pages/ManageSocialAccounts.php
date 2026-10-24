<?php

namespace App\Filament\Resources\SocialAccountResource\Pages;

use App\Filament\Resources\SocialAccountResource;
use App\Models\SocialAccountCredential;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageSocialAccounts extends ManageRecords
{
    protected static string $resource = SocialAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Yeni Sosyal Hesap Bağla')
                ->using(function (array $data): Model {
                    $accessToken = $data['access_token'] ?? null;
                    $refreshToken = $data['refresh_token'] ?? null;
                    $tokenExpiresAt = $data['token_expires_at'] ?? null;

                    unset($data['access_token'], $data['refresh_token'], $data['token_expires_at']);

                    $data['workspace_id'] = 1;
                    if (empty($data['account_id'])) {
                        $data['account_id'] = 'auto_' . uniqid();
                    }
                    $account = static::getModel()::create($data);

                    if ($accessToken) {
                        SocialAccountCredential::create([
                            'social_account_id' => $account->id,
                            'access_token' => $accessToken,
                            'refresh_token' => $refreshToken,
                            'token_expires_at' => $tokenExpiresAt,
                        ]);

                        try {
                            $code = $account->platform?->code instanceof \BackedEnum
                                ? $account->platform->code->value
                                : (string) $account->platform?->code;
                            $publisher = app(\App\Services\Social\SocialPublisherManager::class)->getPublisher($code);
                            if (method_exists($publisher, 'resolveInstagramAccount')) {
                                $publisher->resolveInstagramAccount($account, $accessToken, true);
                            } elseif (method_exists($publisher, 'resolveFacebookPage')) {
                                $publisher->resolveFacebookPage($account, $accessToken);
                            }
                        } catch (\Throwable) {
                            // Ignore resolution error during creation
                        }
                    }

                    return $account;
                }),
        ];
    }
}
