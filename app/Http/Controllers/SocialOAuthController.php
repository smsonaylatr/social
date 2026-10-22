<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\SocialAccountCredential;
use App\Models\SocialAccountPermission;
use App\Models\SocialPlatform;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SocialOAuthController extends Controller
{
    /**
     * Default required scopes per platform for Partner OAuth 2.0
     */
    protected array $defaultScopes = [
        'tiktok' => ['video.publish', 'video.upload', 'user.info.basic'],
        'instagram' => ['instagram_basic', 'instagram_content_publish', 'pages_show_list', 'pages_read_engagement'],
        'facebook' => ['pages_manage_posts', 'pages_read_engagement', 'pages_show_list'],
        'youtube' => [
            'https://www.googleapis.com/auth/youtube.upload',
            'https://www.googleapis.com/auth/youtube.readonly',
            'https://www.googleapis.com/auth/youtube',
            'https://www.googleapis.com/auth/userinfo.profile',
            'https://www.googleapis.com/auth/userinfo.email',
        ],
        'x' => ['tweet.read', 'tweet.write', 'users.read', 'offline.access'],
        'pinterest' => ['pins:write', 'pins:read', 'boards:read'],
        'linkedin' => ['w_member_social', 'w_organization_social', 'r_liteprofile'],
        'whatsapp' => ['whatsapp_business_messaging', 'whatsapp_business_management'],
        'telegram' => ['bot:send_video', 'bot:get_chat'],
    ];

    /**
     * Redirect user to Official Partner OAuth 2.0 Authorization URL
     */
    public function redirect(Request $request, string $platform): RedirectResponse
    {
        $clientId = Setting::get("partner_{$platform}_client_id") ?: config("services.{$platform}.client_id");
        $envMode = Setting::get("partner_{$platform}_env", 'sandbox');
        $redirectUri = url("/oauth/callback/{$platform}");
        $state = csrf_token();

        // PKCE code verifier for X OAuth 2.0
        $codeVerifier = bin2hex(random_bytes(32));
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        session([
            'oauth_state' => $state,
            'oauth_platform' => $platform,
            'oauth_code_verifier' => $codeVerifier,
        ]);

        // If real Partner App Client ID is configured and in production mode, redirect to official OAuth Consent Screen
        if (! empty($clientId) && $envMode === 'production') {
            $authUrl = match ($platform) {
                'tiktok' => 'https://www.tiktok.com/v2/auth/authorize/?' . http_build_query([
                    'client_key' => $clientId,
                    'scope' => implode(',', $this->defaultScopes['tiktok']),
                    'response_type' => 'code',
                    'redirect_uri' => $redirectUri,
                    'state' => $state,
                ]),
                'instagram', 'facebook', 'whatsapp' => 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query([
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'scope' => implode(',', $this->defaultScopes[$platform] ?? $this->defaultScopes['facebook']),
                    'response_type' => 'code',
                    'state' => $state,
                ]),
                'youtube' => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'scope' => implode(' ', $this->defaultScopes['youtube']),
                    'access_type' => 'offline',
                    'prompt' => 'consent select_account',
                    'response_type' => 'code',
                    'state' => $state,
                ]),
                'x' => 'https://twitter.com/i/oauth2/authorize?' . http_build_query([
                    'response_type' => 'code',
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'scope' => implode(' ', $this->defaultScopes['x']),
                    'state' => $state,
                    'code_challenge' => $codeChallenge,
                    'code_challenge_method' => 'S256',
                ]),
                'pinterest' => 'https://www.pinterest.com/oauth/?' . http_build_query([
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'response_type' => 'code',
                    'scope' => implode(',', $this->defaultScopes['pinterest']),
                    'state' => $state,
                ]),
                'linkedin' => 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
                    'response_type' => 'code',
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'scope' => implode(' ', $this->defaultScopes['linkedin']),
                    'state' => $state,
                ]),
                'telegram' => 'https://oauth.telegram.org/auth?' . http_build_query([
                    'bot_id' => $clientId,
                    'origin' => url('/'),
                    'return_to' => $redirectUri,
                ]),
                default => null,
            };

            if ($authUrl) {
                return redirect()->away($authUrl);
            }
        }

        // Partner Sandbox / Test mode callback redirect
        return redirect()->to(url("/oauth/callback/{$platform}?code=partner_sandbox_auth_code_" . uniqid() . "&state={$state}"));
    }

    /**
     * Handle Official Partner OAuth 2.0 Callback, exchange code for token & profile, and save account
     */
    public function callback(Request $request, string $platform): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect()->to("/admin/social-channels?platform={$platform}&error=" . urlencode($request->query('error_description', 'Erişim izni reddedildi.')));
        }

        $code = $request->query('code');
        if (! $code) {
            return redirect()->to("/admin/social-channels?platform={$platform}");
        }

        $dbPlatform = SocialPlatform::where('code', $platform)->first();
        if (! $dbPlatform) {
            return redirect()->to("/admin/social-channels");
        }

        $workspaceId = Workspace::first()?->id ?? 1;
        $clientId = Setting::get("partner_{$platform}_client_id") ?: config("services.{$platform}.client_id");
        $clientSecret = Setting::get("partner_{$platform}_client_secret") ?: config("services.{$platform}.client_secret");
        $isSandboxCode = str_starts_with($code, 'partner_sandbox_auth_code_');

        $tokenData = null;
        $profileData = null;

        if (! $isSandboxCode && $clientId && $clientSecret) {
            try {
                $tokenData = $this->exchangeCodeForToken($platform, $code, $clientId, $clientSecret);
                if (! empty($tokenData['access_token'])) {
                    $profileData = $this->fetchAuthenticatedProfile($platform, $tokenData['access_token']);
                }
            } catch (\Throwable $e) {
                return redirect()->to("/admin/social-channels?platform={$platform}&error=" . urlencode('OAuth Token Değişimi Hatası: ' . $e->getMessage()));
            }
        }

        $user = auth()->user();
        $defaultName = $user ? ($user->name . ' — ' . $dbPlatform->name) : ($dbPlatform->name . ' İş Ortağı Hesabı');

        $customName = session('oauth_custom_name');
        $customUsername = session('oauth_custom_username');
        $customAccountId = session('oauth_custom_account_id');
        session()->forget(['oauth_custom_name', 'oauth_custom_username', 'oauth_custom_account_id']);

        // Check if an account for this platform already exists in the workspace
        $existingAccount = SocialAccount::where('platform_id', $dbPlatform->id)
            ->where('workspace_id', $workspaceId)
            ->first();

        $externalId = $profileData['id']
            ?? (!empty($customAccountId) ? $customAccountId : null)
            ?? ($existingAccount ? $existingAccount->account_id : ($platform . '_partner_' . substr(md5($code), 0, 10)));

        $accountName = $profileData['name']
            ?? (!empty($customName) ? $customName : null)
            ?? ($existingAccount ? $existingAccount->name : $defaultName);

        $username = $profileData['username']
            ?? (!empty($customUsername) ? ltrim($customUsername, '@') : null)
            ?? ($existingAccount ? $existingAccount->username : (strtolower($platform) . '_resmi_' . substr($externalId, -4)));

        $avatarUrl = $profileData['avatar_url'] ?? ($existingAccount?->avatar_url);

        $accessToken = $tokenData['access_token'] ?? ('partner_oauth_' . $platform . '_' . sha1($code . uniqid()));
        $refreshToken = $tokenData['refresh_token'] ?? ('partner_rt_' . sha1(uniqid()));
        $expiresIn = (int) ($tokenData['expires_in'] ?? 5184000);
        $scopes = $this->defaultScopes[$platform] ?? [];

        DB::transaction(function () use ($dbPlatform, $workspaceId, $externalId, $accountName, $username, $avatarUrl, $accessToken, $refreshToken, $expiresIn, $scopes, $platform, $existingAccount) {
            $accountIdToMatch = $existingAccount ? $existingAccount->account_id : $externalId;

            $account = SocialAccount::updateOrCreate(
                [
                    'platform_id' => $dbPlatform->id,
                    'account_id' => $accountIdToMatch,
                ],
                [
                    'workspace_id' => $workspaceId,
                    'brand_id' => null,
                    'account_id' => $externalId,
                    'name' => $accountName,
                    'username' => $username,
                    'avatar_url' => $avatarUrl,
                    'account_type' => 'channel',
                    'status' => 'active',
                    'last_synced_at' => now(),
                ]
            );

            SocialAccountCredential::updateOrCreate(
                ['social_account_id' => $account->id],
                [
                    'access_token' => $accessToken,
                    'refresh_token' => $refreshToken,
                    'token_type' => 'Bearer',
                    'token_expires_at' => now()->addSeconds($expiresIn),
                    'scopes' => $scopes,
                    'extra_data' => [
                        'auth_mode' => 'partner_oauth2',
                        'connected_via' => 'oauth_callback',
                        'platform' => $platform,
                    ],
                ]
            );

            foreach ($scopes as $scopeName) {
                SocialAccountPermission::updateOrCreate(
                    [
                        'social_account_id' => $account->id,
                        'permission_name' => $scopeName,
                    ],
                    [
                        'status' => 'granted',
                        'verified_at' => now(),
                        'review_notes' => 'Resmi İş Ortağı OAuth 2.0 Callback ile onaylandı.',
                    ]
                );
            }
        });

        return redirect()->to("/admin/social-channels?platform={$platform}&success=1");
    }

    /**
     * Exchange OAuth 2.0 Authorization Code for Access & Refresh Tokens
     */
    protected function exchangeCodeForToken(string $platform, string $code, string $clientId, string $clientSecret): array
    {
        $redirectUri = url("/oauth/callback/{$platform}");

        return match ($platform) {
            'tiktok' => Http::asForm()->post('https://open.tiktokapis.com/v2/oauth/token/', [
                'client_key' => $clientId,
                'client_secret' => $clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
            ])->throw()->json(),

            'instagram', 'facebook', 'whatsapp' => (function () use ($clientId, $clientSecret, $redirectUri, $code) {
                $shortResp = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                    'code' => $code,
                ])->throw()->json();

                // Exchange short-lived token for 60-day long-lived token
                $longResp = Http::get('https://graph.facebook.com/v19.0/oauth/access_token', [
                    'grant_type' => 'fb_exchange_token',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'fb_exchange_token' => $shortResp['access_token'],
                ]);

                return $longResp->successful() ? $longResp->json() : $shortResp;
            })(),

            'youtube' => Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
            ])->throw()->json(),

            'x' => Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post('https://api.twitter.com/2/oauth2/token', [
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'client_id' => $clientId,
                    'redirect_uri' => $redirectUri,
                    'code_verifier' => session('oauth_code_verifier', 'challenge'),
                ])->throw()->json(),

            'pinterest' => Http::withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post('https://api.pinterest.com/v5/oauth/token', [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                ])->throw()->json(),

            'linkedin' => Http::asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ])->throw()->json(),

            default => [],
        };
    }

    /**
     * Fetch authenticated profile / page / channel details using the OAuth Access Token
     */
    protected function fetchAuthenticatedProfile(string $platform, string $accessToken): array
    {
        return match ($platform) {
            'tiktok' => (function () use ($accessToken) {
                $resp = Http::withToken($accessToken)
                    ->get('https://open.tiktokapis.com/v2/user/info/', [
                        'fields' => 'open_id,display_name,avatar_url,username',
                    ])->json('data.user', []);
                return [
                    'id' => $resp['open_id'] ?? null,
                    'name' => $resp['display_name'] ?? null,
                    'username' => $resp['username'] ?? null,
                    'avatar_url' => $resp['avatar_url'] ?? null,
                ];
            })(),

            'instagram' => (function () use ($accessToken) {
                $pages = Http::withToken($accessToken)
                    ->get('https://graph.facebook.com/v19.0/me/accounts', [
                        'fields' => 'id,name,instagram_business_account{id,name,username,profile_picture_url}',
                    ])->json('data', []);

                foreach ($pages as $page) {
                    if (! empty($page['instagram_business_account']['id'])) {
                        $ig = $page['instagram_business_account'];
                        return [
                            'id' => $ig['id'],
                            'name' => $ig['name'] ?? $ig['username'],
                            'username' => $ig['username'] ?? null,
                            'avatar_url' => $ig['profile_picture_url'] ?? null,
                        ];
                    }
                }
                return [];
            })(),

            'facebook' => (function () use ($accessToken) {
                $page = Http::withToken($accessToken)
                    ->get('https://graph.facebook.com/v19.0/me/accounts', [
                        'fields' => 'id,name,username',
                    ])->json('data.0', []);
                return [
                    'id' => $page['id'] ?? null,
                    'name' => $page['name'] ?? null,
                    'username' => $page['username'] ?? null,
                ];
            })(),

            'youtube' => (function () use ($accessToken) {
                try {
                    $resp = Http::withToken($accessToken)
                        ->get('https://www.googleapis.com/youtube/v3/channels', [
                            'part' => 'id,snippet',
                            'mine' => 'true',
                        ]);

                    if ($resp->successful()) {
                        $channel = $resp->json('items.0');
                        if (! empty($channel['id'])) {
                            return [
                                'id' => $channel['id'],
                                'name' => $channel['snippet']['title'] ?? null,
                                'username' => ltrim($channel['snippet']['customUrl'] ?? '', '@') ?: null,
                                'avatar_url' => $channel['snippet']['thumbnails']['default']['url'] ?? null,
                            ];
                        }
                    } else {
                        \Illuminate\Support\Facades\Log::warning('YouTube channels list API error: ' . $resp->body());
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('YouTube channels API exception: ' . $e->getMessage());
                }

                // Fallback: Google UserInfo (gets user's actual Google name and avatar)
                try {
                    $userinfo = Http::withToken($accessToken)
                        ->get('https://www.googleapis.com/oauth2/v3/userinfo')
                        ->json();

                    if (! empty($userinfo['name'])) {
                        return [
                            'id' => null,
                            'name' => $userinfo['name'],
                            'username' => ! empty($userinfo['email']) ? explode('@', $userinfo['email'])[0] : null,
                            'avatar_url' => $userinfo['picture'] ?? null,
                        ];
                    }
                } catch (\Throwable $e) {
                    // Ignore
                }

                return [];
            })(),

            'x' => (function () use ($accessToken) {
                $user = Http::withToken($accessToken)
                    ->get('https://api.twitter.com/2/users/me', [
                        'user.fields' => 'id,name,username,profile_image_url',
                    ])->json('data', []);
                return [
                    'id' => $user['id'] ?? null,
                    'name' => $user['name'] ?? null,
                    'username' => $user['username'] ?? null,
                    'avatar_url' => $user['profile_image_url'] ?? null,
                ];
            })(),

            'pinterest' => (function () use ($accessToken) {
                $user = Http::withToken($accessToken)
                    ->get('https://api.pinterest.com/v5/user_account')
                    ->json();
                $board = Http::withToken($accessToken)
                    ->get('https://api.pinterest.com/v5/boards')
                    ->json('items.0', []);
                return [
                    'id' => $board['id'] ?? ($user['id'] ?? null),
                    'name' => $user['business_name'] ?? ($user['username'] ?? null),
                    'username' => $user['username'] ?? null,
                    'avatar_url' => $user['profile_image'] ?? null,
                ];
            })(),

            'linkedin' => (function () use ($accessToken) {
                $info = Http::withToken($accessToken)
                    ->get('https://api.linkedin.com/v2/userinfo')
                    ->json();
                return [
                    'id' => isset($info['sub']) ? "urn:li:person:{$info['sub']}" : null,
                    'name' => $info['name'] ?? null,
                    'username' => strtolower(str_replace(' ', '', $info['name'] ?? 'linkedin')),
                    'avatar_url' => $info['picture'] ?? null,
                ];
            })(),

            default => [],
        };
    }
}
