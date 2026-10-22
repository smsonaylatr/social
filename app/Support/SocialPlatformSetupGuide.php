<?php

namespace App\Support;

class SocialPlatformSetupGuide
{
    /**
     * Get comprehensive setup guide, direct token/code links, and step-by-step instructions for all 9 platforms
     */
    public static function all(): array
    {
        return [
            'instagram' => [
                'code' => 'instagram',
                'name' => 'Instagram',
                'api_name' => 'Meta Graph API v19.0 (Instagram Professional / Reels)',
                'color' => '#e1306c',
                'token_generator_label' => 'Meta Graph API Explorer (Doğrudan Erişim Kodu Al)',
                'token_generator_url' => 'https://developers.facebook.com/tools/explorer/',
                'developer_portal_label' => 'Meta for Developers — Uygulamalarım',
                'developer_portal_url' => 'https://developers.facebook.com/apps/',
                'business_settings_label' => 'Meta Business Suite — Sistem Kullanıcıları (Kalıcı Token)',
                'business_settings_url' => 'https://business.facebook.com/settings/system-users',
                'oauth_url' => url('/oauth/redirect/instagram'),
                'redirect_uri' => url('/oauth/callback/instagram'),
                'webhook_uri' => url('/webhooks/social/instagram'),
                'id_field_hint' => 'Instagram Business Account ID (Örn: 17841405822304914) — Graph API Explorer\'da GET /me/accounts?fields=instagram_business_account sorgusu ile alınır.',
                'scopes' => [
                    'instagram_basic',
                    'instagram_content_publish',
                    'pages_show_list',
                    'pages_read_engagement',
                    'business_management',
                ],
                'steps' => [
                    '1. Ön Hazırlık: Instagram hesabınızı "Profesyonel (İşletme veya İçerik Üretici)" hesaba çevirin ve bir Facebook Sayfasına bağlayın.',
                    '2. Uygulama Oluşturma: developers.facebook.com/apps adresine gidin -> "Uygulama Oluştur" -> "İşletme (Business)" türünü seçin.',
                    '3. Redirect URI Tanımlama: Sol menüden "Facebook Login for Business > Ayarlar" kısmına gidin ve "Geçerli OAuth Yönlendirme URI\'leri" alanına ' . url('/oauth/callback/instagram') . ' adresini ekleyin.',
                    '4. Erişim Kodu (Access Token) Alma: "Meta Graph API Explorer" butonuna tıklayın. Sağ üstten uygulamanızı seçin, izinlere instagram_basic, instagram_content_publish, pages_show_list ekleyin ve "Generate Access Token" butonuna basın.',
                    '5. Hesap ID Öğrenme: Explorer sorgu çubuğuna me/accounts?fields=name,instagram_business_account{id,username} yazıp gönderin. Gelen "id" değerini "Harici Hesap Kimliği (ID)" alanına, ürettiğiniz kodu "Erişim Anahtarı (Access Token)" alanına yapıştırın (veya doğrudan OAuth ile bağlayın).',
                ],
            ],

            'tiktok' => [
                'code' => 'tiktok',
                'name' => 'TikTok',
                'api_name' => 'TikTok for Developers — Content Posting API v2',
                'color' => '#fe2c55',
                'token_generator_label' => 'TikTok Developer Portal — Uygulama & Client Key Al',
                'token_generator_url' => 'https://developers.tiktok.com/apps/',
                'developer_portal_label' => 'TikTok Content Posting API Kurulum Dokümanı',
                'developer_portal_url' => 'https://developers.tiktok.com/doc/content-posting-api-get-started',
                'business_settings_label' => 'TikTok Sandbox Test Hesabı Oluşturucu',
                'business_settings_url' => 'https://developers.tiktok.com/application',
                'oauth_url' => url('/oauth/redirect/tiktok'),
                'redirect_uri' => url('/oauth/callback/tiktok'),
                'webhook_uri' => url('/webhooks/social/tiktok'),
                'id_field_hint' => 'TikTok Open ID (Örn: _00012345abcdef) veya TikTok kullanıcı adınız.',
                'scopes' => [
                    'user.info.basic',
                    'video.upload',
                    'video.publish',
                ],
                'steps' => [
                    '1. Geliştirici Hesabı: developers.tiktok.com adresinde oturum açın ve "Manage Apps > Connect an App" butonuna tıklayın.',
                    '2. Ürün ve İzin Ekleme: Uygulama içinden "Add Products" bölümünden "Login Kit" ve "Content Posting API" modüllerini ekleyin.',
                    '3. Redirect URI Ayarı: Login Kit ayarlarında "Redirect URI" alanına ' . url('/oauth/callback/tiktok') . ' adresini kaydedin.',
                    '4. Scope Seçimi: user.info.basic, video.upload ve video.publish izinlerini aktif edin.',
                    '5. Bağlantı & Erişim Kodu: Uygulama sayfasındaki "Client Key" ve "Client Secret" kodlarını Sosyal Medya Kanalları > TikTok Partner ayarlarına girip "OAuth ile Erişim İzni Al" butonuna tıklayın; erişim kodunuz otomatik alınacaktır.',
                ],
            ],

            'youtube' => [
                'code' => 'youtube',
                'name' => 'YouTube',
                'api_name' => 'Google Cloud — YouTube Data API v3',
                'color' => '#ff0000',
                'token_generator_label' => 'Google OAuth 2.0 Playground (Doğrudan Erişim Kodu Al)',
                'token_generator_url' => 'https://developers.google.com/oauthplayground/?scope=https://www.googleapis.com/auth/youtube.upload%20https://www.googleapis.com/auth/youtube.readonly&access_type=offline&prompt=consent',
                'developer_portal_label' => 'Google Cloud Console — API Kimlik Bilgileri (Client ID)',
                'developer_portal_url' => 'https://console.cloud.google.com/apis/credentials',
                'business_settings_label' => 'YouTube Data API v3 Etkinleştirme Sayfası',
                'business_settings_url' => 'https://console.cloud.google.com/apis/library/youtube.googleapis.com',
                'oauth_url' => url('/oauth/redirect/youtube'),
                'redirect_uri' => url('/oauth/callback/youtube'),
                'webhook_uri' => url('/webhooks/social/youtube'),
                'id_field_hint' => 'YouTube Kanal Kimliği (Örn: UCxxxxxxxxxxxxxxxxxxxxxx) — youtube.com/account_advanced sayfasından kopyalanır.',
                'scopes' => [
                    'https://www.googleapis.com/auth/youtube.upload',
                    'https://www.googleapis.com/auth/youtube.readonly',
                ],
                'steps' => [
                    '1. API Etkinleştirme: Google Cloud Console\'a gidin, yeni bir proje oluşturun ve "YouTube Data API v3" servisini etkinleştirin.',
                    '2. OAuth Client Oluşturma: "Kimlik Bilgileri (Credentials) > Kimlik Bilgisi Oluştur > OAuth İstemci Kimliği" adımlarını izleyin ve "Web Uygulaması" seçin.',
                    '3. Redirect URI Ekleme: "Yetkili Yönlendirme URI\'leri" kısmına ' . url('/oauth/callback/youtube') . ' (ve Playground kullanacaksanız https://developers.google.com/oauthplayground) adresini ekleyin.',
                    '4. Doğrudan Erişim Kodu Alma: Yukarıdaki "Google OAuth 2.0 Playground" butonuna tıklayın -> Sağ üst çark ikonundan kendi Client ID/Secret bilginizi girin -> "Authorize APIs" -> "Exchange authorization code for tokens" butonuna basarak Access Token ve Refresh Token alın.',
                    '5. Kanal ID: YouTube Gelişmiş Hesap Ayarlarından (youtube.com/account_advanced) "Kanal Kimliği (UC...)" değerini "Harici Hesap Kimliği (ID)" alanına yapıştırın.',
                ],
            ],

            'facebook' => [
                'code' => 'facebook',
                'name' => 'Facebook',
                'api_name' => 'Meta Graph API v19.0 — Facebook Pages & Reels',
                'color' => '#1877f2',
                'token_generator_label' => 'Meta Graph API Explorer (Sayfa Erişim Kodu Al)',
                'token_generator_url' => 'https://developers.facebook.com/tools/explorer/',
                'developer_portal_label' => 'Meta for Developers — Uygulamalarım',
                'developer_portal_url' => 'https://developers.facebook.com/apps/',
                'business_settings_label' => 'Access Token Debugger (Süre Uzatma Aracı)',
                'business_settings_url' => 'https://developers.facebook.com/tools/debug/accesstoken/',
                'oauth_url' => url('/oauth/redirect/facebook'),
                'redirect_uri' => url('/oauth/callback/facebook'),
                'webhook_uri' => url('/webhooks/social/facebook'),
                'id_field_hint' => 'Facebook Sayfa Kimliği (Page ID — Örn: 1092837465123) — Sayfanızın "Hakkında > Sayfa Şeffaflığı" bölümünde yazar.',
                'scopes' => [
                    'pages_manage_posts',
                    'pages_read_engagement',
                    'pages_show_list',
                ],
                'steps' => [
                    '1. Meta Uygulaması: developers.facebook.com/apps adresinden İşletme (Business) uygulamanızı açın.',
                    '2. Graph API Explorer: Yukarıdaki "Meta Graph API Explorer" butonuna tıklayın. Sağ taraftaki "User or Page" menüsünden "Get Page Access Token" seçeneğini seçin.',
                    '3. İzinleri Seçin: pages_manage_posts, pages_read_engagement ve pages_show_list izinlerini onaylayın.',
                    '4. Kalıcı Token Alma: Oluşan Page Access Token\'ı kopyalayın, "Access Token Debugger" aracında "Extend Access Token" butonuna basarak 60 günlük / süresiz sayfa anahtarına dönüştürün.',
                    '5. Sayfa ID: GET /me/accounts sorgusundaki "id" değerini "Harici Hesap / Sayfa Kimliği" alanına, token\'ı ise "Erişim Anahtarı" alanına yapıştırın.',
                ],
            ],

            'x' => [
                'code' => 'x',
                'name' => 'X (Twitter)',
                'api_name' => 'X Official API v2 & OAuth 2.0 PKCE',
                'color' => '#111827',
                'token_generator_label' => 'X Developer Portal — Keys and Tokens (Erişim Kodu Al)',
                'token_generator_url' => 'https://developer.x.com/en/portal/projects-and-apps',
                'developer_portal_label' => 'X Developer Portal — Dashboard',
                'developer_portal_url' => 'https://developer.x.com/en/portal/dashboard',
                'business_settings_label' => 'X OAuth 2.0 Dokümantasyonu',
                'business_settings_url' => 'https://developer.x.com/en/docs/authentication/oauth-2-0',
                'oauth_url' => url('/oauth/redirect/x'),
                'redirect_uri' => url('/oauth/callback/x'),
                'webhook_uri' => url('/webhooks/social/x'),
                'id_field_hint' => 'X Kullanıcı ID\'niz (Örn: 15482910384) veya @kullaniciadiniz.',
                'scopes' => [
                    'tweet.read',
                    'tweet.write',
                    'users.read',
                    'offline.access',
                ],
                'steps' => [
                    '1. X Developer Portal: developer.x.com/en/portal/dashboard adresine gidin ve "Projects & Apps" altından uygulamanızı seçin.',
                    '2. Yazma İzni (Read and Write): "User authentication settings > Edit" bölümüne girin ve "App permissions" ayarını mutlaka "Read and write" olarak seçin.',
                    '3. OAuth 2.0 Ayarı: "Type of App" olarak "Web App, Automated App or Bot" seçin ve "Callback URI / Redirect URL" kısmına ' . url('/oauth/callback/x') . ' adresini ekleyin.',
                    '4. Erişim Kodu Alma: Kaydettiğinizde ekrana gelen "OAuth 2.0 Client ID" ve "Client Secret" bilgilerini kopyalayın (veya "Keys and tokens" sekmesinden Access Token & Secret üretin).',
                    '5. Bağlama: Sosyal Medya Kanalları sayfasından Client ID/Secret girip "OAuth ile Erişim İzni Al" butonuna tıklayın veya aldığınız token\'ı doğrudan bu forma yapıştırın.',
                ],
            ],

            'pinterest' => [
                'code' => 'pinterest',
                'name' => 'Pinterest',
                'api_name' => 'Pinterest Business API v5',
                'color' => '#e60023',
                'token_generator_label' => 'Pinterest Developer — My Apps & Token Üretici',
                'token_generator_url' => 'https://developers.pinterest.com/apps/',
                'developer_portal_label' => 'Pinterest API v5 Dokümantasyonu',
                'developer_portal_url' => 'https://developers.pinterest.com/docs/getting-started/set-up-app/',
                'business_settings_label' => 'Pinterest İşletme Merkezi (Business Hub)',
                'business_settings_url' => 'https://www.pinterest.com/business/hub/',
                'oauth_url' => url('/oauth/redirect/pinterest'),
                'redirect_uri' => url('/oauth/callback/pinterest'),
                'webhook_uri' => url('/webhooks/social/pinterest'),
                'id_field_hint' => 'Hedef Pinterest Pano Kimliği (Board ID) veya İşletme Hesabı ID\'niz.',
                'scopes' => [
                    'boards:read',
                    'pins:read',
                    'pins:write',
                    'user_accounts:read',
                ],
                'steps' => [
                    '1. İşletme Hesabı: Pinterest hesabınızın "Business (İşletme)" hesabı olduğundan emin olun.',
                    '2. Uygulama Oluşturma: developers.pinterest.com/apps adresine gidin ve "Connect app" butonuna tıklayın.',
                    '3. Redirect URI Tanımlama: Uygulama detay sayfasında "Redirect URIs" alanına ' . url('/oauth/callback/pinterest') . ' adresini ekleyin.',
                    '4. Doğrudan Erişim Kodu Alma: Aynı sayfanın altındaki "Generate Access Token" bölümünden boards:read, pins:read, pins:write kutucuklarını işaretleyip "Generate token" butonuna tıklayın.',
                    '5. Forma Kaydetme: Üretilen `pina_...` ile başlayan erişim kodunu kopyalayıp "Erişim Anahtarı (Access Token)" alanına yapıştırın.',
                ],
            ],

            'linkedin' => [
                'code' => 'linkedin',
                'name' => 'LinkedIn',
                'api_name' => 'LinkedIn Marketing / Community Management API',
                'color' => '#0a66c2',
                'token_generator_label' => 'LinkedIn OAuth 2.0 Token Generator (Doğrudan Kod Al)',
                'token_generator_url' => 'https://www.linkedin.com/developers/tools/oauth',
                'developer_portal_label' => 'LinkedIn Developers — My Apps',
                'developer_portal_url' => 'https://www.linkedin.com/developers/apps',
                'business_settings_label' => 'LinkedIn Share on LinkedIn Ürün Dokümanı',
                'business_settings_url' => 'https://learn.microsoft.com/en-us/linkedin/consumer/integrations/self-serve/share-on-linkedin',
                'oauth_url' => url('/oauth/redirect/linkedin'),
                'redirect_uri' => url('/oauth/callback/linkedin'),
                'webhook_uri' => url('/webhooks/social/linkedin'),
                'id_field_hint' => 'Şirket Sayfası ID (Örn: urn:li:organization:1234567) veya Kişisel Profil URN (urn:li:person:xxxx).',
                'scopes' => [
                    'w_member_social',
                    'w_organization_social',
                    'r_liteprofile',
                    'openid',
                ],
                'steps' => [
                    '1. Uygulama Oluşturma: linkedin.com/developers/apps adresinden "Create app" butonuna tıklayın ve LinkedIn Şirket Sayfanızı bağlayın.',
                    '2. Ürün Ekleme: "Products" sekmesinden "Share on LinkedIn" ve "Sign In with LinkedIn using OpenID Connect" (şirket paylaşımları için "Advertising API / Community Management API") ürünlerini etkinleştirin.',
                    '3. Redirect URL: "Auth" sekmesindeki "Authorized redirect URLs" kısmına ' . url('/oauth/callback/linkedin') . ' adresini ekleyin.',
                    '4. Doğrudan Erişim Kodu Alma: Yukarıdaki "LinkedIn OAuth 2.0 Token Generator" butonuna tıklayın -> "Create token" -> Uygulamanızı ve w_member_social iznini seçip "Request access token" butonuna basın.',
                    '5. Kaydetme: Ekranda oluşan Access Token\'ı ve şirket/profil ID\'nizi bu forma yapıştırın.',
                ],
            ],

            'whatsapp' => [
                'code' => 'whatsapp',
                'name' => 'WhatsApp',
                'api_name' => 'Meta WhatsApp Business Cloud API v19.0',
                'color' => '#25d366',
                'token_generator_label' => 'Meta App Dashboard — WhatsApp API Kurulumu & Token Al',
                'token_generator_url' => 'https://developers.facebook.com/apps/',
                'developer_portal_label' => 'Meta Business Suite — Sistem Kullanıcıları (Kalıcı Token)',
                'developer_portal_url' => 'https://business.facebook.com/settings/system-users',
                'business_settings_label' => 'WhatsApp Cloud API Dokümantasyonu',
                'business_settings_url' => 'https://developers.facebook.com/docs/whatsapp/cloud-api/get-started',
                'oauth_url' => url('/oauth/redirect/whatsapp'),
                'redirect_uri' => url('/oauth/callback/whatsapp'),
                'webhook_uri' => url('/webhooks/social/whatsapp'),
                'id_field_hint' => 'WhatsApp Phone Number ID (Telefon Numarası Kimliği — Örn: 105928374651234).',
                'scopes' => [
                    'whatsapp_business_messaging',
                    'whatsapp_business_management',
                ],
                'steps' => [
                    '1. Meta Uygulaması: developers.facebook.com/apps üzerinden İşletme uygulamanıza "WhatsApp" ürününü ekleyin.',
                    '2. Phone Number ID Alma: Sol menüden "WhatsApp > API Kurulumu (API Setup)" sayfasına gidin. Buradaki "Phone number ID (Telefon numarası kimliği)" değerini kopyalayıp "Harici Hesap Kimliği (ID)" alanına yapıştırın.',
                    '3. Erişim Kodu (Access Token) Alma: Aynı sayfada test için geçici token alabilir veya "Meta Business Suite > Sistem Kullanıcıları" sayfasından whatsapp_business_messaging iznine sahip kalıcı (Permanent) Access Token üretebilirsiniz.',
                    '4. Webhook Bağlantısı: "WhatsApp > Yapılandırma" sekmesinde Geri Arama URL\'si olarak ' . url('/webhooks/social/whatsapp') . ' ve Doğrulama Anahtarı olarak socialflow_meta_verify girin.',
                ],
            ],

            'telegram' => [
                'code' => 'telegram',
                'name' => 'Telegram',
                'api_name' => 'Telegram Bot & Channel Broadcasting API',
                'color' => '#229ed9',
                'token_generator_label' => 'Telegram @BotFather\'ı Aç (Doğrudan Bot Token Al)',
                'token_generator_url' => 'https://t.me/BotFather',
                'developer_portal_label' => 'Telegram Bot API Resmi Rehberi',
                'developer_portal_url' => 'https://core.telegram.org/bots/api',
                'business_settings_label' => 'Telegram Web (Kanal ID Öğrenme)',
                'business_settings_url' => 'https://web.telegram.org/',
                'oauth_url' => url('/oauth/redirect/telegram'),
                'redirect_uri' => url('/oauth/callback/telegram'),
                'webhook_uri' => url('/webhooks/social/telegram'),
                'id_field_hint' => 'Telegram Kanal Kullanıcı Adı (Örn: @kanaladiniz) veya Kanal Chat ID (Örn: -100192837465).',
                'scopes' => [
                    'bot:send_video',
                    'bot:get_chat',
                ],
                'steps' => [
                    '1. Bot Oluşturma: Yukarıdaki "Telegram @BotFather\'ı Aç" butonuna tıklayın ve sohbete /newbot komutunu gönderin.',
                    '2. Erişim Kodu (Bot Token) Alma: Botunuza bir isim ve kullanıcı adı verdikten sonra BotFather size 712345678:AAHxyz... formatında bir HTTP API Token verecektir. Bu kodu kopyalayıp "Erişim Anahtarı (Access Token)" alanına yapıştırın.',
                    '3. Kanala Yönetici Ekleme: Video paylaşmak istediğiniz Telegram Kanalınızın "Yöneticiler (Administrators)" bölümüne girin ve oluşturduğunuz botu "Mesaj Gönderme (Post Messages)" yetkisiyle yönetici olarak ekleyin.',
                    '4. Kanal Kimliği: Kanalınızın kullanıcı adını (@kanaladi) veya "-100..." ile başlayan Chat ID\'sini "Harici Hesap / Kanal Kimliği (ID)" alanına yazıp kaydedin.',
                ],
            ],
        ];
    }

    public static function get(?string $code): array
    {
        $all = static::all();
        $normalized = strtolower((string) $code);
        return $all[$normalized] ?? $all['instagram'];
    }
}
