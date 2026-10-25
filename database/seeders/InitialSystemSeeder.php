<?php

namespace Database\Seeders;

use App\Enums\SocialPlatformCode;
use App\Enums\UserRole;
use App\Models\Brand;
use App\Models\Hashtag;
use App\Models\HashtagGroup;
use App\Models\KeywordLibrary;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Models\VideoCategory;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialSystemSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Initial Workspace
        $workspace = Workspace::firstOrCreate(
            ['slug' => 'default-workspace'],
            [
                'name' => 'Ana Çalışma Alanı',
                'description' => 'Sosyal medya hesapları ve video kütüphanesi ana çalışma alanı',
                'is_active' => true,
            ]
        );

        // 2. Initial Super Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@socialflow.ai'],
            [
                'name' => 'SocialFlow Admin',
                'password' => 'Admin123!*',
                'role' => UserRole::SUPER_ADMIN,
                'is_active' => true,
            ]
        );

        $workspace->users()->syncWithoutDetaching([
            $admin->id => ['role' => UserRole::SUPER_ADMIN->value],
        ]);

        // 3. Initial Brand: Patenli Ayakkabılar
        $brand = Brand::firstOrCreate(
            ['workspace_id' => $workspace->id, 'slug' => 'patenli-ayakkabilar'],
            [
                'name' => 'Patenli Ayakkabılar',
                'website' => 'https://patenliayakkabilar.com',
                'slogan' => 'Walk. Roll. Fly.',
                'brand_tone' => 'energetic',
                'target_audience' => 'Çocuklar, gençler, ebeveynler, hediye arayanlar ve tekerlekli ayakkabı tutkunları',
                'brand_language' => 'tr',
                'seo_keywords' => [
                    'patenli ayakkabı',
                    'tekerlekli ayakkabı',
                    'çocuk patenli ayakkabı',
                    'ışıklı patenli ayakkabı',
                    'patenli spor ayakkabı',
                    'roller shoes',
                ],
                'forbidden_words' => [
                    'ucuz',
                    'çakma',
                    'taklit',
                    'garantisiz',
                ],
                'default_cta' => 'Hemen incelemek ve sipariş vermek için web sitemizi ziyaret edin! 🛼👟',
                'landing_urls' => [
                    'home' => 'https://patenliayakkabilar.com',
                    'shop' => 'https://patenliayakkabilar.com/koleksiyon',
                    'kids' => 'https://patenliayakkabilar.com/cocuk-patenli-ayakkabi',
                ],
                'campaign_notes' => 'Tüm Türkiye ücretsiz kargo ve %100 değişim garantisi.',
                'is_active' => true,
            ]
        );

        // 4. Default Video Categories
        $categories = [
            ['name' => 'Ürün Tanıtımı', 'slug' => 'urun-tanitimi', 'color' => '#3b82f6'],
            ['name' => 'Müşteri Deneyimi & Yorumlar', 'slug' => 'musteri-deneyimi', 'color' => '#10b981'],
            ['name' => 'Kullanım Rehberi & İpuçları', 'slug' => 'kullanim-rehberi', 'color' => '#f59e0b'],
            ['name' => 'Trend & Eğlenceli Reels', 'slug' => 'trend-reels', 'color' => '#ec4899'],
            ['name' => 'Kampanya & İndirimler', 'slug' => 'kampanya-indirimler', 'color' => '#8b5cf6'],
        ];

        foreach ($categories as $cat) {
            VideoCategory::firstOrCreate(
                ['workspace_id' => $workspace->id, 'slug' => $cat['slug']],
                [
                    'brand_id' => $brand->id,
                    'name' => $cat['name'],
                    'color' => $cat['color'],
                ]
            );
        }

        // 5. Official Social Media Platforms
        $platforms = [
            [
                'code' => SocialPlatformCode::FACEBOOK,
                'name' => 'Facebook',
                'icon' => 'heroicon-o-globe-alt',
                'description' => 'Meta Graph API üzerinden Facebook Sayfaları ve Video/Reels paylaşımı',
                'daily_post_limit' => 25,
                'supported_features' => ['feed_post', 'video', 'reels', 'page_insights'],
            ],
            [
                'code' => SocialPlatformCode::INSTAGRAM,
                'name' => 'Instagram',
                'icon' => 'heroicon-o-camera',
                'description' => 'Meta Graph API üzerinden Instagram Professional/Business Reels ve Video paylaşımı',
                'daily_post_limit' => 25,
                'supported_features' => ['reels', 'video_post', 'insights'],
            ],
            [
                'code' => SocialPlatformCode::YOUTUBE,
                'name' => 'YouTube',
                'icon' => 'heroicon-o-play-circle',
                'description' => 'Google YouTube Data API v3 ile Video ve Shorts yükleme',
                'daily_post_limit' => 10,
                'supported_features' => ['shorts', 'video', 'tags', 'playlists'],
            ],
            [
                'code' => SocialPlatformCode::X,
                'name' => 'X (Twitter)',
                'icon' => 'heroicon-o-chat-bubble-bottom-center-text',
                'description' => 'Resmi X API v2 ile video gönderi paylaşımı',
                'daily_post_limit' => 50,
                'supported_features' => ['video_tweet', 'short_caption'],
            ],
            [
                'code' => SocialPlatformCode::PINTEREST,
                'name' => 'Pinterest',
                'icon' => 'heroicon-o-bookmark',
                'description' => 'Pinterest API v5 ile Video Pin ve pano yönetimi',
                'daily_post_limit' => 30,
                'supported_features' => ['video_pin', 'board_select', 'link'],
            ],
            [
                'code' => SocialPlatformCode::TIKTOK,
                'name' => 'TikTok',
                'icon' => 'heroicon-o-sparkles',
                'description' => 'TikTok Content Posting API ile Creator/Business video paylaşımı',
                'daily_post_limit' => 10,
                'supported_features' => ['video_direct_post', 'caption', 'privacy'],
            ],
            [
                'code' => SocialPlatformCode::LINKEDIN,
                'name' => 'LinkedIn',
                'icon' => 'heroicon-o-briefcase',
                'description' => 'LinkedIn Posts API & Videos API ile Şirket Sayfası ve Profil video paylaşımı',
                'daily_post_limit' => 20,
                'supported_features' => ['video_post', 'organization_page', 'member_post'],
            ],
            [
                'code' => SocialPlatformCode::WHATSAPP,
                'name' => 'WhatsApp',
                'icon' => 'heroicon-o-chat-bubble-oval-left-ellipsis',
                'description' => 'WhatsApp Business Cloud API ile mesajlaşma ve medya katalog paylaşımı',
                'daily_post_limit' => 100,
                'supported_features' => ['direct_message', 'media_share', 'catalog'],
            ],
            [
                'code' => SocialPlatformCode::TELEGRAM,
                'name' => 'Telegram',
                'icon' => 'heroicon-o-paper-airplane',
                'description' => 'Telegram Bot & Channel API ile kanal ve topluluk paylaşımları',
                'daily_post_limit' => 100,
                'supported_features' => ['channel_post', 'video_message', 'media_group'],
            ],
        ];

        foreach ($platforms as $platformData) {
            SocialPlatform::firstOrCreate(
                ['code' => $platformData['code']->value],
                [
                    'name' => $platformData['name'],
                    'icon' => $platformData['icon'],
                    'description' => $platformData['description'],
                    'daily_post_limit' => $platformData['daily_post_limit'],
                    'supported_features' => $platformData['supported_features'],
                    'is_active' => true,
                ]
            );
        }

        // 6. Initial Hashtags for Patenli Ayakkabılar (Zengin Hashtag Havuzu)
        $hashtagGroupsData = [
            [
                'name' => 'Marka & Temel Ürün',
                'category' => 'brand',
                'color' => '#3b82f6',
                'priority' => 10,
                'tags' => [
                    'patenliayakkabi', 'patenliayakkabilar', 'walkrollfly', 'tekerlekliayakkabi',
                    'rollershoes', 'tekerleklisporayakkabi', 'ayakkabipaten', 'patensporu',
                    'heelysturkiye', 'cocukpatenliayakkabi', 'orijinalpatenliayakkabi', 'tekerleklipaten',
                ],
            ],
            [
                'name' => 'Çocuk & Hediye Etiketleri',
                'category' => 'kids',
                'color' => '#10b981',
                'priority' => 8,
                'tags' => [
                    'cocukmodasi', 'cocukhediyesi', 'dogumgunuhediyesi', 'isikliayakkabi',
                    'eglenceliayakkabi', 'sporyapancocuklar', 'cocukgiyim', 'cocukaktivite',
                    'karnehediyesi', 'erkekcocukhediye', 'kizcocukhediye', 'surprizhediye',
                    'cocuktrendleri', 'eniyicocukhediyesi', 'mutlucocuklar',
                ],
            ],
            [
                'name' => 'Trend, Keşfet & Viral',
                'category' => 'trending',
                'color' => '#f59e0b',
                'priority' => 9,
                'tags' => [
                    'kesfet', 'kesfetteyiz', 'viral', 'reels', 'fyp', 'trend',
                    'kesfetbeni', 'kesfetteyim', 'trendvideolar', 'viraltiktok',
                    'reelsvideo', 'shorts', 'shortsvideo', 'tiktoktrend', 'instareels',
                    'onecikar', 'viralreels', 'kesfetpostu',
                ],
            ],
            [
                'name' => 'Paten & Sokak Eğlencesi',
                'category' => 'general',
                'color' => '#ec4899',
                'priority' => 7,
                'tags' => [
                    'patenkeyfi', 'patenkaymak', 'sokakmodasi', 'eglence', 'spor',
                    'aktivite', 'haftasonuaktivitesi', 'kaykayvepaten', 'patenciler',
                    'ozgurluk', 'hareketet', 'saglikliyasam', 'aksiyon', 'tarz',
                ],
            ],
            [
                'name' => 'Moda & Online Alışveriş',
                'category' => 'product',
                'color' => '#8b5cf6',
                'priority' => 7,
                'tags' => [
                    'alisveris', 'alisverisonerisi', 'onlinealisveris', 'indirim',
                    'guvenlialisveris', 'hizlikargo', 'sporayakkabi', 'trendayakkabi',
                    'tarzayakkabi', 'yeniurun', 'kampanya', 'firsat', 'tarzcocuklar',
                ],
            ],
        ];

        foreach ($hashtagGroupsData as $gData) {
            $group = HashtagGroup::firstOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $gData['name']],
                ['brand_id' => $brand->id, 'category' => $gData['category'], 'color' => $gData['color']]
            );

            foreach ($gData['tags'] as $tag) {
                Hashtag::firstOrCreate(
                    ['workspace_id' => $workspace->id, 'tag' => $tag],
                    [
                        'group_id' => $group->id,
                        'category' => $gData['category'],
                        'priority' => $gData['priority'],
                        'is_active' => true,
                    ]
                );
            }
        }

        // 7. Initial SEO Keywords
        $keywords = [
            ['keyword' => 'patenli ayakkabı', 'priority' => 10, 'search_intent' => 'transactional'],
            ['keyword' => 'tekerlekli ayakkabı', 'priority' => 9, 'search_intent' => 'transactional'],
            ['keyword' => 'çocuk patenli ayakkabı', 'priority' => 9, 'search_intent' => 'commercial'],
            ['keyword' => 'ışıklı patenli ayakkabı', 'priority' => 8, 'search_intent' => 'commercial'],
            ['keyword' => 'patenli spor ayakkabı', 'priority' => 8, 'search_intent' => 'commercial'],
            ['keyword' => 'roller shoes türkiye', 'priority' => 7, 'search_intent' => 'navigational'],
        ];

        foreach ($keywords as $kw) {
            KeywordLibrary::firstOrCreate(
                ['workspace_id' => $workspace->id, 'keyword' => $kw['keyword']],
                [
                    'brand_id' => $brand->id,
                    'priority' => $kw['priority'],
                    'search_intent' => $kw['search_intent'],
                    'language' => 'tr',
                    'target_url' => 'https://patenliayakkabilar.com',
                    'is_active' => true,
                ]
            );
        }
    }
}
