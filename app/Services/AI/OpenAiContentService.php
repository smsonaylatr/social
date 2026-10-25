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
            $rawTitle = $hasTitle ? ($pData['title'] ?? '') : null;
            
            // OpenAI bazen "x" veya "linkedin" için "caption" yerine "tweet", "text", "post", "content" dönebilir
            $candidateCaption = $pData['caption'] ?? $pData['tweet'] ?? $pData['text'] ?? $pData['post'] ?? $pData['content'] ?? null;
            if (empty(trim((string) $candidateCaption))) {
                $candidateCaption = $video->custom_caption ?: null;
            }
            if (empty(trim((string) $candidateCaption))) {
                $candidateCaption = 'Patenli ayakkabı ve tekerlekli spor ayakkabı modelleri ile yürümek artık çok daha eğlenceli! ✨';
            }
            $rawCaption = $candidateCaption;
            $rawHook = $pData['hook_sentence'] ?? $pData['hook'] ?? '';

            // Sistem içi "4. Video", "1. Video" gibi terimleri kesinlikle temizle
            $platformTitle = $rawTitle ? self::cleanSystemLabels($rawTitle) : null;
            $platformCaption = self::cleanSystemLabels($rawCaption);
            if (empty(trim((string) $platformCaption))) {
                $platformCaption = 'Patenli ayakkabı modelleri ile gün boyu özgürce kay! 🚀';
            }
            $platformHook = self::cleanSystemLabels($rawHook);

            $defaultHashtags = ['PatenliAyakkabı', 'PatenliAyakkabılar', 'TekerlekliAyakkabı', 'ÇocukPatenliAyakkabı'];
            $hashtags = ! empty($pData['hashtags']) && is_array($pData['hashtags']) ? $pData['hashtags'] : $defaultHashtags;
            $productUrl = ! empty($pData['suggested_product_url']) ? $pData['suggested_product_url'] : ($video->product_url ?: ($brand?->website ?? 'https://patenliayakkabilar.com'));

            $platforms[$code] = new GeneratedPlatformContentDTO(
                platformCode: $code,
                title: $platformTitle,
                hookSentence: $platformHook,
                caption: $platformCaption,
                hashtags: $hashtags,
                seoKeywords: $pData['seo_keywords'] ?? [],
                callToAction: $pData['call_to_action'] ?? ($brand?->default_cta ?? 'Hemen keşfedin! 🛍️'),
                suggestedProductUrl: $productUrl,
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

        $seoService = app(\App\Services\SEO\HashtagOptimizationService::class);
        $seoKeywords = $seoService->getRelevantKeywords($video, limit: 6);
        if (empty($seoKeywords)) {
            $seoKeywords = $brand?->seo_keywords ?? [
                'patenli ayakkabı', 'tekerlekli ayakkabı', 'çocuk patenli ayakkabı', 'ışıklı patenli ayakkabı'
            ];
        }

        $kw1 = $seoKeywords[0] ?? 'patenli ayakkabı';
        $kw2 = $seoKeywords[1] ?? 'tekerlekli ayakkabı';
        $kw3 = $seoKeywords[2] ?? 'çocuk patenli ayakkabı';
        $kw4 = $seoKeywords[3] ?? 'ışıklı patenli ayakkabı';

        $hooks = [
            'Sıradan yürüyüşleri unutun! ✨',
            'Yürümek güzel ama kayarak ilerlemek çok daha eğlenceli! 🔥',
            'Onun yeni favori ayakkabısı olmaya hazır! 🎁',
            'Ekran başından kaldıracak, hareketi sevdirecek bir fikir arayanlara! ⚡',
            'Adım at, topuğa bas ve kaymaya başla! 🚀',
            'Hem şık bir spor ayakkabı hem de gizli bir paten! 🌟',
        ];

        $bodies = [
            "Hem şık bir spor ayakkabı hem de tek hareketle eğlenceli bir paten! {$kw3} ve {$kw2} modellerimizle çocuklar için hareket dolu bir deneyim.",
            "Hem günlük kullanıma uygun rahat bir spor ayakkabı, hem de tek hareketle kaymaya hazır {$kw1}! {$kw4} ve {$kw2} koleksiyonu ile dışarıda aktif vakit geçirmek çok keyifli.",
            "Sadece bir ayakkabı değil, gün boyu süren bir eğlence! {$kw1} modelleri hem günlük hayatta rahatça kullanılır, hem de gizli tekerlekleriyle anında {$kw2} ve patene dönüşür.",
            "Çocuklara unutulmaz bir sürpriz yapmak isteyen ailelerin bir numaralı tercihi {$kw3}! İster okulda ve sokakta yürüyüş yap, ister gizli {$kw2} ile özgürce kay.",
            "Hafta sonu gezmelerini festivale dönüştüren {$kw4} ve {$kw1} modelleri! Konforlu iç tabanı ve dayanıklı gizli tekerlek mekanizmasıyla her adımda maksimum heyecan.",
        ];

        $ytTitles = [
            '✨ Patenli Ayakkabı Modelleri | Tek Hareketle Patene Dönüşen Tekerlekli Ayakkabı! 🎁 #Shorts',
            '🔥 Çocuk Patenli Ayakkabı & Işıklı Tekerlekli Spor Ayakkabı Modelleri! 👟 #patenliayakkabı',
            '⚡ Tek Hareketle Patene Dönüşen Efsane Patenli Ayakkabı! ✨ #tekerlekliayakkabı #Shorts',
            '🎁 Çocukları Ekran Başından Kaldıran Hediye: Patenli Ayakkabılar! 🌟 #patenliayakkabı',
            '🚀 Hem Spor Ayakkabı Hem Paten Bir Arada | Tekerlekli Ayakkabı Modelleri 👟 #Shorts',
        ];

        $fbTitles = [
            '✨ Patenli Ayakkabı ve Tekerlekli Spor Ayakkabı Modelleri | Patenli Ayakkabılar',
            '🎁 Çocuklar İçin En Unutulmaz Hediye: Çocuk Patenli Ayakkabı & Işıklı Modeller',
            '🔥 Hem Günlük Spor Ayakkabı Hem Tekerlekli Patenli Ayakkabı Bir Arada!',
            '⚡ Sokakları Piste Dönüştüren Patenli Ayakkabı ve Tekerlekli Ayakkabı Fiyatları',
        ];

        $xCaptions = [
            "Patenli ayakkabı ve tekerlekli ayakkabı modelleriyle sokaklar artık bir pist! 👟✨\n\nÇocuk patenli ayakkabı ve ışıklı patenli spor ayakkabı koleksiyonunu hemen keşfet 🛍️\n🌐 {$productUrl}",
            "Çocuk patenli ayakkabı arayanlara tek hareketle patene dönüşen efsane tekerlekli ayakkabı! 🎁✨\n\nIşıklı patenli ayakkabı modelleri ve fiyatları için 🛍️\n🌐 {$productUrl}",
            "Hem günlük spor ayakkabı hem gizli tekerlekli paten! 🚀\n\nPatenli ayakkabı modelleri, çocuk patenli ayakkabı ve tekerlekli spor ayakkabı fırsatlarını incele 🛍️\n🌐 {$productUrl}",
            "Yürümek güzel ama patenli ayakkabı ile kaymak çok daha eğlenceli! 🔥\n\nÇocuklar için ışıklı tekerlekli ayakkabı ve patenli spor ayakkabı modelleri 🛍️\n🌐 {$productUrl}",
            "Ekran başından kaldıran en özel hediye: Patenli ayakkabı! ⚡\n\nGizli tekerlekli ayakkabı ve çocuk patenli ayakkabı modellerinde hızlı kargo 🛍️\n🌐 {$productUrl}",
        ];

        $bullets = "✨ Dikkat çeken modern patenli ayakkabı tasarımları\n👟 Spor ayakkabı + tekerlekli paten deneyimi bir arada\n🎁 Çocuk patenli ayakkabı & ışıklı modellerle harika hediye\n🚚 Hızlı kargo • Güvenli alışveriş";

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
        $xCaption = $xCaptions[array_rand($xCaptions)];

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
                caption: $xCaption,
                hashtags: ['PatenliAyakkabı', 'TekerlekliAyakkabı', 'ÇocukPatenliAyakkabı'],
                seoKeywords: $seoKeywords,
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
                title: 'Patenli Ayakkabı ve Tekerlekli Spor Ayakkabı Modelleri | Patenli Ayakkabılar',
                hookSentence: 'Çocuk patenli ayakkabı ve tekerlekli ayakkabı modellerimizle hareketi eğlenceye dönüştürüyoruz! ⚡',
                caption: "Çocuk patenli ayakkabı ve tekerlekli ayakkabı modellerimizle hareketi eğlenceye dönüştürüyoruz! ⚡\n\n{$brandName} olarak çocukların ve gençlerin severek giydiği patenli ayakkabı, ışıklı patenli ayakkabı ve gizli tekerlekli spor ayakkabı modelleri sunuyoruz. Hem günlük kullanıma uygun konforlu spor ayakkabı, hem de tek hareketle kaymaya hazır paten deneyimi bir arada!\n\n{$bullets}\n\nTüm patenli ayakkabı modelleri ve fiyatları için resmi web sitemizi ziyaret edin:\n🌐 patenliayakkabilar.com",
                hashtags: ['PatenliAyakkabı', 'TekerlekliAyakkabı', 'ÇocukPatenliAyakkabı', 'IşıklıPatenliAyakkabı', 'PatenliAyakkabılar'],
                seoKeywords: $seoKeywords,
                callToAction: 'Patenli ayakkabı modellerini inceleyin: patenliayakkabilar.com',
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
KRİTİK İSİMLENDİRME VE METİN KURALLARI (HAYATİ ÖNEM TAŞIR!):
══════════════════════════════════════════════════════════════════════════════════
- Tanıtılan ürün kesinlikle "Patenli Ayakkabı" / "Tekerlekli Spor Ayakkabı"dır (tek tıkla patene dönüşen ayakkabılar).
- KESİNLİKLE "1. Video", "2. Video", "4. Video", "video", "klip", dosya adı veya numara gibi iç sistem etiketlerini metne, kancaya veya açıklamalara YAZMA!
- Cümlelerinde doğrudan üründen ve özelliklerinden bahset (Örn: "Patenli Ayakkabılar ile yürümek artık çok daha eğlenceli!", "Işıklı tekerlekli spor ayakkabı modelleri").

══════════════════════════════════════════════════════════════════════════════════
KRİTİK SEO ANAHTAR KELİME VE HAFIZA KURALI (HAYATİ ÖNEM TAŞIR!):
══════════════════════════════════════════════════════════════════════════════════
- Paylaşılan gönderiler "SEO Anahtar Kelime Hafızası"ndan mutlaka beslenmelidir.
- Sana verilen SEO anahtar kelimelerini (seo_keywords) başlıkta (title), kanca cümlesinde (hook) veya açıklama metninin (caption) maddeleri içinde MUTLAKA doğal bir şekilde geçir ve kullan.
- Çıktıdaki JSON'da "seo_keywords" alanında bu platform için kullandığın anahtar kelimeleri eksiksiz listele.

══════════════════════════════════════════════════════════════════════════════════
KRİTİK EMOJİ VE MASAÜSTÜ UYUMLULUK KURALLARI (HAYATİ ÖNEM TAŞIR!):
══════════════════════════════════════════════════════════════════════════════════
- Windows masaüstü Chrome/Edge/Firefox tarayıcılarında görünmeyen veya içi boş kare [ ] (tofu box) şeklinde bozuk çıkan paten ("\\u{1F6FC}"), buz pateni ("\\u{26F8}"), kaykay ("\\u{1F6F9}") emojilerini KESİNLİKLE KULLANMA!
- SADECE tüm masaüstü ve mobil işletim sistemlerinde %100 sorunsuz ve net görünen evrensel emojileri kullan:
  ✨, 🔥, ⚡, 🎁, 👟, 🚚, 🛍️, 🌐, 📸, 📲, 👉, 👇, 🛒, 🌟, 🚀, 💬, 💖, ⭐, 🎉, 💯

══════════════════════════════════════════════════════════════════════════════════
İÇERİK ŞABLON YAPISI (DÜZ PARAGRAF KESİNLİKLE YASAKTIR!):
══════════════════════════════════════════════════════════════════════════════════
Tüm platformlar için üretilen "caption" metinleri kesinlikle tek parça düz metin veya düz cümleler olamaz!
Aşağıdaki gibi boş satırlarla (\n\n) ayrılmış, satır satır, maddeli ve emojili görsel şablon bloklarından oluşmak ZORUNDADIR:

1) Vurucu Kanca (1. satır, sonu ✨, 🔥, ⚡ veya 🎁 emojisiyle biten merak uyandırıcı cümle)
2) Boş satır (\n\n)
3) 3-4 Maddelik Vurgu Listesi (Satır başlarında mutlaka ✨, 👟, 🎁, 🚚 emojileri olmalı):
   ✨ Dikkat çeken modern patenli ayakkabı tasarımları
   👟 Günlük ayakkabı konforu + paten eğlencesi bir arada
   🎁 Çocuklar ve gençler için unutulmaz bir hediye
   🚚 Hızlı kargo • Güvenli alışveriş
4) Boş satır (\n\n)
5) Eylem Çağrısı (CTA) & Yönlendirme:
   👉 Modelleri ve fiyatları hemen keşfet:
   🌐 patenliayakkabilar.com
   📸 @patenliayakkabilar

══════════════════════════════════════════════════════════════════════════════════
X (TWITTER) İÇİN ZORUNLU MADDELİ ŞABLON (KESİNLİKLE DÜZ YAZI YAZMA!):
══════════════════════════════════════════════════════════════════════════════════
X için üretilen caption metni KESİNLİKLE düz 2 cümle olmayacaktır! X'te de yukarıdaki gibi satır satır, boşluklu ve maddeli şablon kullanılacaktır (Max 240 karakter):

Örnek X Metni:
Adım at, topuğa bas ve kaymaya başla! 🚀

✨ Tek hareketle patene dönüşen ayakkabı
👟 Çocuk & yetişkin patenli ayakkabılar
🚚 Hızlı kargo • Güvenli alışveriş

🛍️ Hemen sipariş verin:
🌐 https://patenliayakkabilar.com

* YouTube başlığı: Max 100 karakter, dikkat çekici, başında/sonunda masaüstü uyumlu emoji (örn: ✨ Onun Yeni Favori Ayakkabısı Olmaya Hazır! 🎁 #patenliayakkabı).

Çıktıyı kesinlikle aşağıdaki JSON şemasına uygun tek bir JSON objesi olarak üret. Caption alanlarında satır sonları için mutlaka "\n\n" ve "\n" karakterlerini kullan:
{
  "instagram": {
    "title": null,
    "hook_sentence": "Adım at, topuğa bas ve kaymaya başla! 🚀",
    "caption": "Adım at, topuğa bas ve kaymaya başla! 🚀\n\nHem şık bir spor ayakkabı hem tek hareketle eğlenceli bir paten! Çocuk patenli ayakkabı modellerimizle hareket dolu bir deneyim.\n\n✨ Dikkat çeken modern tasarımlar\n👟 Ayakkabı + paten deneyimi bir arada\n🎁 Doğum günü için harika bir hediye\n🚚 Hızlı kargo • Güvenli alışveriş\n\n👉 Detaylı bilgi ve sipariş için:\n🌐 https://patenliayakkabilar.com\n📸 @patenliayakkabilar",
    "hashtags": ["PatenliAyakkabı", "PatenliAyakkabılar", "ÇocukPatenliAyakkabı", "TekerlekliAyakkabı", "RollerShoes"],
    "seo_keywords": ["patenli ayakkabı", "çocuk patenli ayakkabı"],
    "call_to_action": "Profildeki linkten hemen sipariş verin! 🛍️",
    "suggested_product_url": "https://patenliayakkabilar.com"
  },
  "facebook": {
    "title": "✨ Patenli Ayakkabı Modelleri | Patenli Ayakkabılar",
    "hook_sentence": "Yürümek güzel ama kayarak ilerlemek çok daha eğlenceli! 🔥",
    "caption": "Yürümek güzel ama kayarak ilerlemek çok daha eğlenceli! 🔥\n\nHem günlük kullanıma uygun spor ayakkabı hem de tek hareketle paten!\n\n✨ Dikkat çeken modern tasarımlar\n👟 Rahat spor ayakkabı konforu\n🚚 Hızlı kargo • Güvenli alışveriş\n\n👉 Detaylı bilgi ve sipariş için:\n🌐 https://patenliayakkabilar.com",
    "hashtags": ["PatenliAyakkabı", "PatenliAyakkabılar", "TekerlekliAyakkabı"],
    "seo_keywords": ["patenli ayakkabı"],
    "call_to_action": "Detaylı bilgi ve sipariş için tıklayın",
    "suggested_product_url": "https://patenliayakkabilar.com"
  },
  "youtube": {
    "title": "✨ Tek Hareketle Patene Dönüşen Efsane Patenli Ayakkabı! 🎁 #patenliayakkabı",
    "hook_sentence": "Tek hareketle patene dönüşen ayakkabıyı gördünüz mü? ⚡",
    "caption": "Tek hareketle patene dönüşen ayakkabıyı gördünüz mü? ⚡\n\nGünlük spor ayakkabıyı tek hareketle patene dönüştürün!\n\n✨ Modern ve dayanıklı tasarım\n👟 Gizli tekerlek mekanizması\n🚚 Hızlı kargo • Güvenli alışveriş\n\n🌐 Resmi Web Sitesi: https://patenliayakkabilar.com\n📸 @patenliayakkabilar",
    "hashtags": ["Shorts", "PatenliAyakkabı", "PatenliAyakkabılar", "TekerlekliAyakkabı"],
    "seo_keywords": ["patenli ayakkabı"],
    "call_to_action": "Kanalımıza abone olun ve yeni modelleri keşfedin!",
    "suggested_product_url": "https://patenliayakkabilar.com"
  },
  "x": {
    "title": null,
    "hook_sentence": "Adım at, topuğa bas ve kaymaya başla! 🚀",
    "caption": "Adım at, topuğa bas ve kaymaya başla! 🚀\n\n✨ Tek hareketle patene dönüşen ayakkabı\n👟 Çocuk & yetişkin patenli ayakkabılar\n🚚 Hızlı kargo • Güvenli alışveriş\n\n🛍️ Hemen sipariş verin:\n🌐 https://patenliayakkabilar.com",
    "hashtags": ["PatenliAyakkabı", "TekerlekliAyakkabı"],
    "seo_keywords": ["patenli ayakkabı"],
    "call_to_action": "Hemen tıkla!",
    "suggested_product_url": "https://patenliayakkabilar.com"
  },
  "pinterest": {
    "title": "✨ Çocuk Patenli Ayakkabı Modelleri & Tekerlekli Spor Ayakkabı",
    "hook_sentence": "Çocuklar için en havalı patenli ayakkabılar! 🎁",
    "caption": "Çocuklar için en havalı patenli ayakkabılar! 🎁\n\n✨ Dikkat çeken modern tasarımlar\n👟 Ayakkabı + paten bir arada\n🚚 Hızlı kargo • Güvenli alışveriş\n\n🌐 https://patenliayakkabilar.com",
    "hashtags": ["PatenliAyakkabı", "ÇocukHediyesi"],
    "seo_keywords": ["patenli ayakkabı"],
    "call_to_action": "Ürünü İncele ve Satın Al",
    "suggested_product_url": "https://patenliayakkabilar.com"
  },
  "tiktok": {
    "title": null,
    "hook_sentence": "Kaymayı sevenler buraya! 🔥",
    "caption": "Kaymayı sevenler buraya! 🔥\n\n✨ Tek tıkla patene dönüşen ayakkabı\n👟 Çocuk ve yetişkin modelleri\n🚚 Hızlı teslimat\n\n🌐 https://patenliayakkabilar.com",
    "hashtags": ["PatenliAyakkabı", "RollerShoes", "fyp", "kesfet"],
    "seo_keywords": ["patenli ayakkabı"],
    "call_to_action": "Takip etmeyi ve videoyu kaydetmeyi unutma!",
    "suggested_product_url": "https://patenliayakkabilar.com"
  },
  "linkedin": {
    "title": null,
    "hook_sentence": "Yenilikçi ayakkabı ve mobilite konsepti: Patenli Ayakkabılar 🚀",
    "caption": "Yenilikçi ayakkabı ve mobilite konsepti: Patenli Ayakkabılar 🚀\n\nŞehir hayatında eğlence ve fonksiyonelliği bir araya getiren tekerlekli spor ayakkabı teknolojisi.\n\n✨ İnovatif tasarım ve mühendislik\n👟 Günlük konfor ve gizli mekanizma\n🚚 Güvenli e-ticaret altyapısı\n\n🌐 https://patenliayakkabilar.com",
    "hashtags": ["İnovasyon", "PatenliAyakkabı", "Girişimcilik", "E-Ticaret"],
    "seo_keywords": ["patenli ayakkabı"],
    "call_to_action": "Koleksiyonu inceleyin",
    "suggested_product_url": "https://patenliayakkabilar.com"
  }
}
PROMPT;
    }

    protected function buildUserPrompt(Video $video, ?Brand $brand): string
    {
        $productCategory = 'Patenli Ayakkabı & Tekerlekli Spor Ayakkabı';
        $titleForAi = $video->title;

        // "4. Video", "1. Video" gibi iç sistem başlıklarını OpenAI'ya ürün adı gibi gönderme!
        if (empty($titleForAi) || preg_match('/^\d+[\.\s\-]+video/i', $titleForAi) || preg_match('/^video[\.\s\-]*\d*$/i', $titleForAi)) {
            $titleForAi = $productCategory;
        }

        $seoService = app(\App\Services\SEO\HashtagOptimizationService::class);
        $seoKeywords = $seoService->getRelevantKeywords($video, limit: 6);
        if (empty($seoKeywords)) {
            $seoKeywords = $brand?->seo_keywords ?? [
                'patenli ayakkabı', 'tekerlekli ayakkabı', 'çocuk patenli ayakkabı', 'ışıklı patenli ayakkabı'
            ];
        }

        return json_encode([
            'product_name' => $titleForAi,
            'product_category' => $productCategory,
            'original_filename' => $video->original_filename,
            'duration_seconds' => $video->duration_seconds,
            'aspect_ratio' => $video->aspect_ratio,
            'custom_caption' => $video->custom_caption,
            'product_url' => $video->product_url ?: ($brand?->website ?? 'https://patenliayakkabilar.com'),
            'seo_keywords' => $seoKeywords,
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Metin içindeki istenmeyen "4. Video", "1. Video ile" gibi iç sistem kalıntılarını temizler
     */
    public static function cleanSystemLabels(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // "4. Video ile", "1. Video ile" -> "Patenli Ayakkabılar ile"
        $cleaned = preg_replace('/\b\d+[\.\s\-]+(?:video|klip|bölüm)\s+ile\b/iu', 'Patenli Ayakkabılar ile', $text);
        // "4. Video", "1. Video" -> "Patenli Ayakkabı"
        $cleaned = preg_replace('/\b\d+[\.\s\-]+(?:video|klip|bölüm)\b/iu', 'Patenli Ayakkabı', $cleaned);
        // "Video 4", "Klip 1" -> "Patenli Ayakkabı"
        $cleaned = preg_replace('/\b(?:video|klip)\s+\d+\b/iu', 'Patenli Ayakkabı', $cleaned);

        return trim($cleaned);
    }
}
