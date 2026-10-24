<?php

namespace App\Services\AI;

use App\Enums\SocialPlatformCode;
use App\Models\AiContentGeneration;
use App\Models\ApiUsageLog;
use App\Models\Brand;
use App\Models\Setting;
use App\Models\Video;
use App\Services\AI\Contracts\AiContentProviderInterface;
use App\Services\AI\DTOs\GeneratedPlatformContentDTO;
use App\Services\AI\DTOs\MultiPlatformContentGenerationResultDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiContentService implements AiContentProviderInterface
{
    protected ?string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key') ?: env('OPENAI_API_KEY') ?: Setting::get('openai_api_key');
        $this->model = config('services.openai.model') ?: Setting::get('openai_model', 'gpt-4o-mini');
    }

    public function isConfigured(): bool
    {
        $key = config('services.openai.api_key') ?: env('OPENAI_API_KEY') ?: Setting::get('openai_api_key');
        return ! empty($key) && ! str_starts_with($key, 'mock_');
    }

    /**
     * Generate content for all 7 platforms for a video
     */
    public function generateForVideo(Video $video, ?Brand $brand = null): MultiPlatformContentGenerationResultDTO
    {
        $brand = $brand ?: $video->brand ?: Brand::first();

        if (! $this->isConfigured() || \Illuminate\Support\Facades\Cache::get('openai_quota_exhausted')) {
            return $this->generateTestModeContent($video, $brand);
        }

        try {
            return $this->callOpenAiApi($video, $brand);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), '429') || str_contains($e->getMessage(), 'credit_balance_exhausted') || str_contains($e->getMessage(), 'insufficient_quota')) {
                \Illuminate\Support\Facades\Cache::put('openai_quota_exhausted', true, now()->addMinutes(15));
            }
            Log::warning('OpenAI API call failed, using dynamic smart library engine: ' . $e->getMessage());
            return $this->generateTestModeContent($video, $brand, true, $e->getMessage());
        }
    }

    /**
     * Generate content for a single platform
     */
    public function generateForSinglePlatform(Video $video, string $platformCode, ?Brand $brand = null): array
    {
        $multiResult = $this->generateForVideo($video, $brand);
        $platformDto = $multiResult->getPlatform($platformCode);

        if ($platformDto) {
            return $platformDto->toArray();
        }

        return [];
    }

    /**
     * Call OpenAI structured JSON API
     */
    protected function callOpenAiApi(Video $video, ?Brand $brand): MultiPlatformContentGenerationResultDTO
    {
        $this->apiKey = config('services.openai.api_key') ?: env('OPENAI_API_KEY') ?: Setting::get('openai_api_key');
        $this->model = config('services.openai.model') ?: Setting::get('openai_model', 'gpt-4o-mini');

        $startTime = microtime(true);
        $systemPrompt = $this->buildSystemPrompt($brand);
        $userPrompt = $this->buildUserPrompt($video, $brand);

        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7,
            ]);

        $durationMs = (microtime(true) - $startTime) * 1000;

        if (! $response->successful()) {
            throw new \RuntimeException("OpenAI API returned {$response->status()}: " . $response->body());
        }

        $data = $response->json();
        $usage = $data['usage'] ?? [];
        $promptTokens = $usage['prompt_tokens'] ?? 0;
        $completionTokens = $usage['completion_tokens'] ?? 0;

        // Pricing estimate (gpt-4o-mini: ~$0.15/1M in, $0.60/1M out)
        $costUsd = ($promptTokens * 0.00000015) + ($completionTokens * 0.00000060);

        // Log usage
        ApiUsageLog::create([
            'workspace_id' => $video->workspace_id,
            'provider' => 'openai',
            'endpoint' => '/v1/chat/completions',
            'method' => 'POST',
            'status_code' => $response->status(),
            'response_time_ms' => $durationMs,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'estimated_cost_usd' => $costUsd,
            'is_error' => false,
        ]);

        $content = json_decode($data['choices'][0]['message']['content'] ?? '{}', true);

        $platforms = [];
        $platformCodes = ['instagram', 'facebook', 'youtube', 'x', 'pinterest', 'tiktok', 'linkedin'];

        foreach ($platformCodes as $code) {
            $pData = $content[$code] ?? [];
            $hasTitle = in_array($code, ['youtube', 'pinterest', 'facebook'], true);
            $platformTitle = $hasTitle ? ($pData['title'] ?? $video->title) : null;

            $platforms[$code] = new GeneratedPlatformContentDTO(
                platformCode: $code,
                title: $platformTitle,
                hookSentence: $pData['hook_sentence'] ?? '',
                caption: $pData['caption'] ?? $video->title,
                hashtags: $pData['hashtags'] ?? [],
                seoKeywords: $pData['seo_keywords'] ?? [],
                callToAction: $pData['call_to_action'] ?? ($brand?->default_cta ?? ''),
                suggestedProductUrl: $pData['suggested_product_url'] ?? $video->product_url,
                isTestMode: false
            );

            // Save to database
            AiContentGeneration::updateOrCreate(
                ['video_id' => $video->id, 'platform_code' => $code],
                [
                    'brand_id' => $brand?->id,
                    'title' => $platforms[$code]->title,
                    'hook_sentence' => $platforms[$code]->hookSentence,
                    'caption' => $platforms[$code]->caption,
                    'hashtags' => $platforms[$code]->hashtags,
                    'seo_keywords' => $platforms[$code]->seoKeywords,
                    'call_to_action' => $platforms[$code]->callToAction,
                    'suggested_product_url' => $platforms[$code]->suggestedProductUrl,
                    'ai_model' => $this->model,
                    'prompt_tokens' => (int) round($promptTokens / 7),
                    'completion_tokens' => (int) round($completionTokens / 7),
                    'cost_usd' => $costUsd / 7,
                    'status' => 'approved',
                ]
            );
        }

        return new MultiPlatformContentGenerationResultDTO(
            platforms: $platforms,
            modelUsed: $this->model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            costUsd: $costUsd,
            isTestMode: false
        );
    }

    /**
     * Fallback / Dynamic Smart Library content generator with multi-variation rotation
     */
    protected function generateTestModeContent(Video $video, ?Brand $brand, bool $isFallback = false, ?string $fallbackReason = null): MultiPlatformContentGenerationResultDTO
    {
        $brandName = $brand?->name ?? 'Patenli Ayakkabılar';
        $productUrl = $video->product_url ?: ($brand?->website ?? 'https://patenliayakkabilar.com');

        $seoKeywords = $brand?->seo_keywords ?? [
            'patenli ayakkabı', 'tekerlekli ayakkabı', 'çocuk patenli ayakkabı', 'ışıklı patenli ayakkabı'
        ];

        $hooks = [
            'Sıradan yürüyüşleri unutun! ✨',
            'Yürümek güzel ama kayarak ilerlemek çok daha eğlenceli! 🔥',
            'Onun yeni favori ayakkabısı olmaya hazır! 🎁',
            'Ekran başından kaldıracak, hareketi sevdirecek bir fikir arayanlara! ⚡',
            'Adım at, topuğa bas ve kaymaya başla! 🚀',
            'Hem şık bir spor ayakkabı hem de gizli bir paten! 🌟',
        ];

        $bodies = [
            'Hem şık bir spor ayakkabı hem de tek hareketle eğlenceli bir paten! Çocuklar için hem hareket dolu hem de çok keyifli bir deneyim.',
            'Hem günlük kullanıma uygun rahat bir ayakkabı, hem de tek hareketle kaymaya hazır eğlenceli bir paten! Çocukların dışarıda daha aktif ve neşeli vakit geçirmesi için tasarlandı.',
            'Sadece bir ayakkabı değil, aynı zamanda gün boyu süren bir eğlence! Hem şık tasarımıyla günlük hayatta rahatça kullanılır, hem de gizli tekerlekleriyle anında patene dönüşür.',
            'Çocuklara unutulmaz bir sürpriz yapmak isteyen ailelerin bir numaralı tercihi! İster okulda ve sokakta yürüyüş yap, ister tekerleği çıkarıp özgürce kay.',
            'Hafta sonu gezmelerini ve park saatlerini festivale dönüştürün! Konforlu iç tabanı ve dayanıklı gizli tekerlek mekanizmasıyla her adımda maksimum heyecan sunuyor.',
        ];

        $ytTitles = [
            '✨ Onun Yeni Favori Ayakkabısı Olmaya Hazır! 🎁 #patenliayakkabı',
            '🔥 Yürümek Güzel Ama Kayarak İlerlemek Çok Daha Eğlenceli! 👟 #Shorts',
            '⚡ Tek Hareketle Patene Dönüşen Efsane Spor Ayakkabı! ✨ #patenliayakkabı',
            '🎁 Çocukları Ekran Başından Kaldıran En Eğlenceli Hediye! 🌟 #Shorts',
            '🚀 Hem Spor Ayakkabı Hem Paten Bir Arada! 👟 #tekerlekliayakkabı',
        ];

        $fbTitles = [
            '✨ Ekran Başından Kaldıracak Eğlenceli Fikir: Patenli Ayakkabılar',
            '🎁 Çocuklar İçin En Unutulmaz Doğum Günü Hediyesi: Patenli Ayakkabılar',
            '🔥 Hem Günlük Spor Ayakkabı Hem Eğlenceli Bir Paten Bir Arada!',
            '⚡ Sokakları Piste Dönüştüren Tekerlekli Spor Ayakkabı Modelleri',
        ];

        $bullets = "✨ Dikkat çeken modern tasarımlar\n👟 Ayakkabı + paten deneyimi bir arada\n🎁 Doğum günü ve özel günler için farklı bir hediye\n🚚 Hızlı kargo • Güvenli alışveriş";

        $igHook = $hooks[array_rand($hooks)];
        $igBody = $bodies[array_rand($bodies)];

        $fbHook = $hooks[array_rand($hooks)];
        $fbBody = $bodies[array_rand($bodies)];
        $fbTitle = $fbTitles[array_rand($fbTitles)];

        $ytHook = $hooks[array_rand($hooks)];
        $ytBody = $bodies[array_rand($bodies)];
        $ytTitle = $ytTitles[array_rand($ytTitles)];

        $tkHook = $hooks[array_rand($hooks)];
        $tkBody = $bodies[array_rand($bodies)];

        $xHook = $hooks[array_rand($hooks)];

        $platforms = [
            'instagram' => new GeneratedPlatformContentDTO(
                platformCode: 'instagram',
                title: null,
                hookSentence: $igHook,
                caption: "{$igHook}\n\n{$igBody}\n\n{$bullets}\n\nÇocuk patenli ayakkabı ve tekerlekli ayakkabı modellerini şimdi keşfet! 🛍️\n\n🌐 patenliayakkabilar.com\n📸 @patenliayakkabilar",
                hashtags: ['PatenliAyakkabı', 'PatenliAyakkabılar', 'ÇocukPatenliAyakkabı', 'TekerlekliAyakkabı', 'RollerShoes'],
                seoKeywords: $seoKeywords,
                callToAction: 'Profildeki linkten hemen sipariş verin! 🛍️',
                suggestedProductUrl: $productUrl,
                isTestMode: true
            ),
            'facebook' => new GeneratedPlatformContentDTO(
                platformCode: 'facebook',
                title: $fbTitle,
                hookSentence: $fbHook,
                caption: "{$fbHook}\n\n{$fbBody}\n\n{$bullets}\n\nÇocuk patenli ayakkabı ve tekerlekli ayakkabı modellerini şimdi keşfet! 🛍️\n\n👉 Detaylı bilgi ve sipariş için: {$productUrl}\n📸 @patenliayakkabilar",
                hashtags: ['PatenliAyakkabı', 'PatenliAyakkabılar', 'ÇocukPatenliAyakkabı', 'TekerlekliAyakkabı', 'RollerShoes'],
                seoKeywords: $seoKeywords,
                callToAction: "Detaylı bilgi ve sipariş için: {$productUrl}",
                suggestedProductUrl: $productUrl,
                isTestMode: true
            ),
            'youtube' => new GeneratedPlatformContentDTO(
                platformCode: 'youtube',
                title: $ytTitle,
                hookSentence: $ytHook,
                caption: "{$ytHook}\n\n{$ytBody}\n\n{$bullets}\n\nÇocuk patenli ayakkabı ve tekerlekli ayakkabı modellerini şimdi keşfet! 🛍️\n\n🌐 Resmi Web Sitesi: {$productUrl}\n📸 @patenliayakkabilar",
                hashtags: ['Shorts', 'PatenliAyakkabı', 'PatenliAyakkabılar', 'TekerlekliAyakkabı', 'RollerShoes'],
                seoKeywords: $seoKeywords,
                callToAction: 'Kanalımıza abone olun ve yeni modelleri ilk siz keşfedin!',
                suggestedProductUrl: $productUrl,
                isTestMode: true
            ),
            'x' => new GeneratedPlatformContentDTO(
                platformCode: 'x',
                title: null,
                hookSentence: $xHook,
                caption: "{$xHook}\n\nTek hareketle patene dönüşen spor ayakkabılarla sokaklar artık bir pist! 👟✨\n\nÇocukların yeni favorisini şimdi keşfet 🛍️\n🌐 patenliayakkabilar.com",
                hashtags: ['PatenliAyakkabı', 'TekerlekliAyakkabı'],
                seoKeywords: array_slice($seoKeywords, 0, 2),
                callToAction: "Hemen tıkla: {$productUrl}",
                suggestedProductUrl: $productUrl,
                isTestMode: true
            ),
            'pinterest' => new GeneratedPlatformContentDTO(
                platformCode: 'pinterest',
                title: "✨ Çocuk Patenli Ayakkabı Modelleri & Tekerlekli Spor Ayakkabı",
                hookSentence: 'Çocuklar ve gençler için en havalı patenli spor ayakkabı modelleri!',
                caption: "{$igHook}\n\n{$igBody}\n\n{$bullets}\n\nÇocuk patenli ayakkabı modellerini şimdi keşfet! 🛍️\n🌐 patenliayakkabilar.com",
                hashtags: ['PatenliAyakkabı', 'PatenliAyakkabılar', 'ÇocukHediyesi', 'TekerlekliAyakkabı'],
                seoKeywords: $seoKeywords,
                callToAction: 'Ürünü İncele ve Satın Al',
                suggestedProductUrl: $productUrl,
                isTestMode: true
            ),
            'tiktok' => new GeneratedPlatformContentDTO(
                platformCode: 'tiktok',
                title: null,
                hookSentence: $tkHook,
                caption: "{$tkHook}\n\n{$tkBody}\n\n{$bullets}\n\n🌐 patenliayakkabilar.com",
                hashtags: ['PatenliAyakkabı', 'PatenliAyakkabılar', 'TekerlekliAyakkabı', 'RollerShoes', 'fyp', 'kesfet'],
                seoKeywords: $seoKeywords,
                callToAction: 'Takip etmeyi ve videoyu kaydetmeyi unutma!',
                suggestedProductUrl: $productUrl,
                isTestMode: true
            ),
            'linkedin' => new GeneratedPlatformContentDTO(
                platformCode: 'linkedin',
                title: null,
                hookSentence: 'Fiziksel aktiviteyi eğlenceli bir deneyime dönüştüren yeni nesil tekerlekli ayakkabı tasarımlarımız. ⚡',
                caption: "Fiziksel aktiviteyi eğlenceli bir deneyime dönüştüren yeni nesil tekerlekli ayakkabı tasarımlarımız. ⚡\n\n{$brandName} olarak genç neslin hareket kabiliyetini ve spor sevgisini artıran inovatif ayakkabı çözümleri sunuyoruz. Ergonomik taban yapısı ve gizlenebilir mekanizması ile sokak modası ve eğlence bir arada.\n\n{$bullets}\n\nÜrünlerimizi ve detayları web sitemizden inceleyebilirsiniz:\n🌐 patenliayakkabilar.com",
                hashtags: ['PatenliAyakkabı', 'İnovasyon', 'Eticaret', 'Perakende', 'Girişimcilik'],
                seoKeywords: ['e-ticaret', 'ürün inovasyonu', 'patenli ayakkabı'],
                callToAction: 'Ürün geliştirme ve kurumsal çözümlerimiz için web sitemizi ziyaret edin.',
                suggestedProductUrl: $productUrl,
                isTestMode: true
            ),
        ];

        $modelName = ($isFallback && str_contains((string) $fallbackReason, 'credit_balance_exhausted'))
            ? 'smart-library-engine'
            : 'smart-library-engine';

        // Persist to database with approved status (ready to publish)
        foreach ($platforms as $code => $dto) {
            AiContentGeneration::updateOrCreate(
                ['video_id' => $video->id, 'platform_code' => $code],
                [
                    'brand_id' => $brand?->id,
                    'title' => $dto->title,
                    'hook_sentence' => $dto->hookSentence,
                    'caption' => $dto->caption,
                    'hashtags' => $dto->hashtags,
                    'seo_keywords' => $dto->seoKeywords,
                    'call_to_action' => $dto->callToAction,
                    'suggested_product_url' => $dto->suggestedProductUrl,
                    'ai_model' => $modelName,
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'cost_usd' => 0,
                    'status' => 'approved',
                ]
            );
        }

        return new MultiPlatformContentGenerationResultDTO(
            platforms: $platforms,
            modelUsed: $modelName,
            promptTokens: 0,
            completionTokens: 0,
            costUsd: 0,
            isTestMode: true
        );
    }

    protected function buildSystemPrompt(?Brand $brand): string
    {
        $brandName = $brand?->name ?? 'Patenli Ayakkabılar';
        $slogan = $brand?->slogan ?? 'Walk. Roll. Fly.';
        $brandTone = $brand?->brand_tone ?? 'energetic';
        $forbidden = implode(', ', $brand?->forbidden_words ?? []);

        return <<<PROMPT
Sen kıdemli bir Sosyal Medya İçerik Mimarı ve Sosyal SEO uzmanısın.
Görevin, verilen video metadata ve marka profiline dayanarak aşağıdaki 7 sosyal medya platformu için tam uyumlu, özgün, yüksek dönüşüm sağlayan Türkçe içerik üretmektir:
1. instagram (Reels odaklı, samimi kanca, emojiler, ilgili hashtagler. DİKKAT: Instagram'da ayrı başlık YOKTUR, title boş olmalıdır.)
2. facebook (Fayda odaklı, aile ve ebeveyn dostu, video başlığı, bağlantı yönlendirmesi)
3. youtube (Shorts ve arama odaklı SEO başlığı - ZORUNLU max 100 karakter, detaylı açıklama, etiketler)
4. x (Twitter: DİKKAT: Başlık YOKTUR, 280 karakteri aşmayan dinamik tweet metni)
5. pinterest (Görsel SEO Pin başlığı - ZORUNLU max 100 karakter, arama açıklaması, landing URL)
6. tiktok (Trendlere uygun, genç kitle dili, izleyici etkileşimi, CTA. DİKKAT: Başlık YOKTUR, title boş olmalıdır.)
7. linkedin (Marka ve perakende vizyonu, profesyonel ama samimi gönderi metni. DİKKAT: Gönderide ayrı başlık YOKTUR.)

Marka Bilgileri:
- Marka: {$brandName}
- Slogan: {$slogan}
- Ton: {$brandTone}
- Kesinlikle Yasaklı Kelimeler: {$forbidden}

══════════════════════════════════════════════════════════════════════════════════
KRİTİK EMOJİ VE MASAÜSTÜ UYUMLULUK KURALLARI (HAYATİ ÖNEM TAŞIR!):
══════════════════════════════════════════════════════════════════════════════════
- Windows masaüstü Chrome/Edge/Firefox tarayıcılarında görünmeyen veya içi boş kare [ ] (tofu box) şeklinde bozuk çıkan paten ("\\u{1F6FC}"), buz pateni ("\\u{26F8}"), kaykay ("\\u{1F6F9}") emojilerini KESİNLİKLE KULLANMA!
- SADECE tüm masaüstü ve mobil işletim sistemlerinde %100 sorunsuz ve net görünen evrensel emojileri kullan:
  ✨, 🔥, ⚡, 🎁, 👟, 🚚, 🛍️, 🌐, 📸, 📲, 👉, 👇, 🛒, 🌟, 🚀, 💬, 💖, ⭐, 🎉, 💯

══════════════════════════════════════════════════════════════════════════════════
İÇERİK ŞABLON YAPISI (ÖRNEK KÜTÜPHANEYE BİREBİR UYGUN):
══════════════════════════════════════════════════════════════════════════════════
İçeriklerin gövdesi şu 5 aşamalı dönüşüm şablonunu takip etmelidir:
1) Vurucu Kanca (1. satır, sonu ✨, 🔥, ⚡ veya 🎁 emojisiyle biten merak uyandırıcı soru/cümle)
2) Gövde Metni (2-3 cümlelik samimi ve spor ayakkabı + paten konforunu anlatan kısa paragraf)
3) 4 Maddelik Vurgu Listesi:
   ✨ Dikkat çeken modern tasarımlar
   👟 Ayakkabı + paten deneyimi bir arada
   🎁 Doğum günü ve özel günler için farklı bir hediye
   🚚 Hızlı kargo • Güvenli alışveriş
4) Eylem Çağrısı (CTA) & Yönlendirme:
   Çocuk patenli ayakkabı ve tekerlekli ayakkabı modellerini şimdi keşfet! 🛍️
   🌐 patenliayakkabilar.com
   📸 @patenliayakkabilar
5) En Etkili 5 SEO Hashtag (DİKKAT: Hashtag'leri caption metninin sonuna YAZMA, yalnızca "hashtags" dizisinde döndür; yayınlanırken sistem otomatik birleştirir):
   ["PatenliAyakkabı", "PatenliAyakkabılar", "ÇocukPatenliAyakkabı", "TekerlekliAyakkabı", "RollerShoes"]

* X (Twitter) için bu metni 280 karakter limitine sığacak şekilde özetle (ana kanca + ayakkabı deneyimi + site linki + 2 ana hashtag).
* YouTube başlığı: Max 100 karakter, dikkat çekici, başında/sonunda masaüstü uyumlu emoji (örn: ✨ Onun Yeni Favori Ayakkabısı Olmaya Hazır! 🎁 #patenliayakkabı).

Çıktıyı kesinlikle aşağıdaki JSON şemasına uygun tek bir JSON objesi olarak üret:
{
  "instagram": { "title": null, "hook_sentence": "...", "caption": "...", "hashtags": ["..."], "seo_keywords": ["..."], "call_to_action": "...", "suggested_product_url": "..." },
  "facebook": { "title": "...", "hook_sentence": "...", "caption": "...", "hashtags": ["..."], "seo_keywords": ["..."], "call_to_action": "...", "suggested_product_url": "..." },
  "youtube": { "title": "...", "hook_sentence": "...", "caption": "...", "hashtags": ["..."], "seo_keywords": ["..."], "call_to_action": "...", "suggested_product_url": "..." },
  "x": { "title": null, "hook_sentence": "...", "caption": "...", "hashtags": ["..."], "seo_keywords": ["..."], "call_to_action": "...", "suggested_product_url": "..." },
  "pinterest": { "title": "...", "hook_sentence": "...", "caption": "...", "hashtags": ["..."], "seo_keywords": ["..."], "call_to_action": "...", "suggested_product_url": "..." },
  "tiktok": { "title": null, "hook_sentence": "...", "caption": "...", "hashtags": ["..."], "seo_keywords": ["..."], "call_to_action": "...", "suggested_product_url": "..." },
  "linkedin": { "title": null, "hook_sentence": "...", "caption": "...", "hashtags": ["..."], "seo_keywords": ["..."], "call_to_action": "...", "suggested_product_url": "..." }
}
PROMPT;
    }

    protected function buildUserPrompt(Video $video, ?Brand $brand): string
    {
        return json_encode([
            'video_title' => $video->title,
            'original_filename' => $video->original_filename,
            'duration_seconds' => $video->duration_seconds,
            'aspect_ratio' => $video->aspect_ratio,
            'custom_caption' => $video->custom_caption,
            'product_url' => $video->product_url ?: $brand?->website,
            'seo_keywords' => $brand?->seo_keywords,
        ], JSON_UNESCAPED_UNICODE);
    }
}
