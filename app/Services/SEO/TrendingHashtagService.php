<?php

namespace App\Services\SEO;

use App\Models\Brand;
use App\Models\Hashtag;
use App\Models\HashtagGroup;
use App\Models\Workspace;
use App\Services\AI\Contracts\AiContentProviderInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TrendingHashtagService
{
    /**
     * Toplu girilen etiketleri temizler, mükerrerleri ayıklar ve revaçtaki etiket olarak havuza kaydeder
     */
    public function bulkImport(
        string|array $tagsInput,
        ?string $platformCode = null,
        string $category = 'trending',
        int $priority = 9,
        ?Carbon $expiresAt = null,
        ?int $groupId = null,
        ?int $workspaceId = null,
        bool $isTrending = true
    ): array {
        $workspaceId = $workspaceId ?: (Workspace::first()?->id ?? 1);

        // Metin olarak geldiyse virgül, yeni satır veya boşluklara göre ayır
        if (is_string($tagsInput)) {
            $rawList = preg_split('/[\r\n,;\s]+/u', $tagsInput, -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $rawList = $tagsInput;
        }

        $cleanedTags = [];
        foreach ($rawList as $raw) {
            $tag = ltrim(trim((string) $raw), '#');
            // Boşlukları ve özel karakterleri temizle
            $tag = preg_replace('/[^\p{L}\p{N}_]/u', '', $tag);
            if ($tag !== '' && ! in_array(mb_strtolower($tag, 'UTF-8'), array_map(fn ($t) => mb_strtolower($t, 'UTF-8'), $cleanedTags))) {
                $cleanedTags[] = $tag;
            }
        }

        if (empty($cleanedTags)) {
            return [
                'added_count' => 0,
                'existing_count' => 0,
                'total_processed' => 0,
                'tags' => [],
            ];
        }

        // Eğer grup belirtilmemişse "Revaçtaki & Trend Etiketler" grubunu bul veya oluştur
        if (! $groupId) {
            $group = HashtagGroup::firstOrCreate(
                [
                    'workspace_id' => $workspaceId,
                    'name' => 'Revaçtaki Trend Etiketler',
                ],
                [
                    'category' => 'trending',
                    'color' => '#f59e0b',
                ]
            );
            $groupId = $group->id;
        }

        $addedCount = 0;
        $existingCount = 0;
        $savedTags = [];

        foreach ($cleanedTags as $tag) {
            $existing = Hashtag::where('workspace_id', $workspaceId)
                ->where('tag', $tag)
                ->first();

            if ($existing) {
                // Mevcut etiketi revaçtaki olarak güncelle
                $existing->update([
                    'is_trending' => $isTrending,
                    'category' => $category,
                    'priority' => max($existing->priority, $priority),
                    'platform_filter' => $platformCode ?: $existing->platform_filter,
                    'expires_at' => $expiresAt ?: $existing->expires_at,
                    'is_active' => true,
                ]);
                $existingCount++;
                $savedTags[] = $existing->tag;
            } else {
                Hashtag::create([
                    'workspace_id' => $workspaceId,
                    'group_id' => $groupId,
                    'tag' => $tag,
                    'category' => $category,
                    'is_trending' => $isTrending,
                    'platform_filter' => $platformCode,
                    'priority' => $priority,
                    'usage_count' => 0,
                    'expires_at' => $expiresAt,
                    'is_active' => true,
                ]);
                $addedCount++;
                $savedTags[] = $tag;
            }
        }

        return [
            'added_count' => $addedCount,
            'existing_count' => $existingCount,
            'total_processed' => count($cleanedTags),
            'tags' => $savedTags,
        ];
    }

    /**
     * Platformlara özel hazır revaçtaki preset paketleri
     */
    public function getPresetPacks(): array
    {
        return [
            'tiktok_viral' => [
                'name' => 'TikTok FYP & Viral Akım Paketi',
                'platform' => 'tiktok',
                'description' => 'TikTok algoritmasında keşfete düşmeyi hızlandıran revaçtaki akım etiketleri',
                'tags' => [
                    'fyp', 'foryou', 'foryoupage', 'viral', 'viraltiktok',
                    'tiktoktrend', 'kesfetteyiz', 'kesfetbeni', 'kesfetteyim',
                    'rollershoes', 'patenliayakkabi', 'eglence', 'akim',
                    'trendvideolar', 'tekerlekliayakkabi', 'dance', 'cocuktrendleri',
                ],
            ],
            'instagram_reels' => [
                'name' => 'Instagram Reels & Keşfet Paketi',
                'platform' => 'instagram',
                'description' => 'Reels keşfet algoritması ve yüksek etkileşim için revaçtaki etiketler',
                'tags' => [
                    'reels', 'reelsvideo', 'instareels', 'kesfet', 'kesfetteyiz',
                    'viralreels', 'trendingreels', 'instagood', 'patenliayakkabi',
                    'cocukmodasi', 'reelsviral', 'reelsinstagram', 'kesfetpostu',
                    'trend', 'tekerlekliayakkabi', 'mutlucocuklar', 'hediyeonerisi',
                ],
            ],
            'youtube_shorts' => [
                'name' => 'YouTube Shorts Trend Paketi',
                'platform' => 'youtube',
                'description' => 'Shorts feed ve aramalarda öne çıkaran popüler YouTube etiketleri',
                'tags' => [
                    'shorts', 'shortsvideo', 'youtubeshorts', 'viralshorts',
                    'trendingshorts', 'trend', 'shortsfyp', 'patenliayakkabi',
                    'eglence', 'cocuk', 'rollershoes', 'tekerlekliayakkabi',
                    'patensporu', 'haftasonuaktivitesi',
                ],
            ],
            'x_gundem' => [
                'name' => 'X (Twitter) Gündem & Popüler Paket',
                'platform' => 'x',
                'description' => 'X platformunun kısa ve vurucu trend etiketleri',
                'tags' => [
                    'PatenliAyakkabı', 'TekerlekliAyakkabı', 'Alışveriş',
                    'Trend', 'ÇocukHediyesi', 'SokakModası',
                ],
            ],
            'pinterest_ilham' => [
                'name' => 'Pinterest Trend Fikirler & Hediye Paketi',
                'platform' => 'pinterest',
                'description' => 'Pinterest görsel aramalarda yüksek tıklama alan trend etiketler',
                'tags' => [
                    'cocukhediyesi', 'dogumgunuhediyesi', 'hediyeonerisi',
                    'cocukmodasi', 'patenliayakkabi', 'sokakmodasi',
                    'tarzcocuklar', 'isikliayakkabi', 'alisveris', 'trendfikirler',
                ],
            ],
            'global_trend' => [
                'name' => 'Tüm Platformlar Genel Trend Paketi',
                'platform' => null,
                'description' => 'Tüm sosyal medya kanallarında geçerli viral ve popüler etiketler',
                'tags' => [
                    'kesfet', 'viral', 'trend', 'eglence', 'patenliayakkabi',
                    'tekerlekliayakkabi', 'rollershoes', 'cocukhediyesi',
                    'tarz', 'hareketet', 'onlinealisveris', 'yeniurun',
                ],
            ],
        ];
    }

    /**
     * Hazır bir preset paketini tek tıkla havuza yükler
     */
    public function loadPreset(string $presetKey, ?int $workspaceId = null, ?int $groupId = null): array
    {
        $presets = $this->getPresetPacks();
        if (! isset($presets[$presetKey])) {
            throw new \InvalidArgumentException("Geçersiz preset paketi: {$presetKey}");
        }

        $pack = $presets[$presetKey];

        return $this->bulkImport(
            tagsInput: $pack['tags'],
            platformCode: $pack['platform'],
            category: 'trending',
            priority: 9,
            groupId: $groupId,
            workspaceId: $workspaceId,
            isTrending: true
        );
    }

    /**
     * OpenAI / Yapay Zeka kullanarak seçilen platform ve konu için revaçtaki hashtagleri üretir
     */
    public function generateTrendingWithAi(string $platformCode, ?string $topic = null, int $count = 25): array
    {
        $brand = Brand::first();
        $brandContext = $brand ? "Marka: {$brand->name}. Sektör: Patenli ayakkabı, çocuk spor ve eğlence ayakkabısı." : "Sektör: Patenli spor ayakkabı modelleri, çocuk ve genç eğlencesi.";
        $topicContext = $topic ? "Özel Odak / Konu: {$topic}." : "Genel odak: Sosyal medyada keşfete düşüren, viral, yüksek etkileşimli ve trend başlıklar.";

        $platformName = match (strtolower($platformCode)) {
            'instagram' => 'Instagram (Reels ve Keşfet algoritmaları için)',
            'tiktok' => 'TikTok (FYP, akım ve viral videolar için)',
            'youtube' => 'YouTube Shorts (Öne çıkanlar ve trend arama sonuçları için)',
            'x' => 'X / Twitter (Gündem, kısa ve vurucu etiketler için)',
            'pinterest' => 'Pinterest (İlham, hediye ve alışveriş arama niyetleri için)',
            'facebook' => 'Facebook (Video ve sayfa etkileşimleri için)',
            'linkedin' => 'LinkedIn (Kurumsal, e-ticaret ve girişimcilik için)',
            default => 'Tüm sosyal medya platformları',
        };

        $prompt = "Sen uzman bir sosyal medya büyüme ve trend analisti yapay zekasısın.\n"
            . "Hedef Platform: {$platformName}\n"
            . "{$brandContext}\n"
            . "{$topicContext}\n\n"
            . "GÖREV:\n"
            . "Bu platformda şu anda EN ÇOK İZLENEN, REVAÇTA OLAN, KEŞFETE DÜŞÜREN ve etkileşimi patlatan TAM {$count} ADET popüler/revaçtaki Türkçe ve evrensel hashtag üret.\n\n"
            . "KURALLAR:\n"
            . "1. Her etiketin başında # işareti OLMASIN (sadece kelime/etiket metni).\n"
            . "2. Boşluk içermesin, geçerli hashtag formatında olsun (Örn: patenliayakkabi, kesfetteyiz, cocuktrendleri, viralreels).\n"
            . "3. JSON formatında sadece string dizisi döndür: [\"etiket1\", \"etiket2\", ...]\n"
            . "4. Kesinlikle başka açıklama veya markdown ekleme, sadece saf JSON array üret.";

        try {
            $apiKey = config('services.openai.api_key') ?: \App\Models\Setting::get('openai_api_key');
            if (empty($apiKey)) {
                return $this->getFallbackTrendingTags($platformCode);
            }

            $response = \Illuminate\Support\Facades\Http::withToken($apiKey)
                ->timeout(20)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Sen sosyal medya trendlerini analiz eden bir JSON veri üreticisisin. Sadece JSON array çıktısı verirsin.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.7,
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content');
                $cleaned = preg_replace('/```(?:json)?|```/i', '', trim($content));
                $tags = json_decode($cleaned, true);

                if (is_array($tags) && ! empty($tags)) {
                    return array_values(array_unique(array_filter(array_map(function ($t) {
                        return preg_replace('/[^\p{L}\p{N}_]/u', '', ltrim(trim($t), '#'));
                    }, $tags))));
                }
            }
        } catch (\Throwable $e) {
            Log::warning("AI Trending hashtag generation failed: " . $e->getMessage());
        }

        return $this->getFallbackTrendingTags($platformCode);
    }

    /**
     * AI servisi ulaşılamazsa zengin yedek trend listesi döner
     */
    protected function getFallbackTrendingTags(string $platformCode): array
    {
        $presets = $this->getPresetPacks();
        $key = match (strtolower($platformCode)) {
            'tiktok' => 'tiktok_viral',
            'instagram' => 'instagram_reels',
            'youtube' => 'youtube_shorts',
            'x' => 'x_gundem',
            'pinterest' => 'pinterest_ilham',
            default => 'global_trend',
        };

        return $presets[$key]['tags'] ?? $presets['global_trend']['tags'];
    }
}
