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
    public function generateForVideo(Video $video, ?Brand $brand = null, bool $force = false): MultiPlatformContentGenerationResultDTO
    {
        $brand = $brand ?: $video->brand ?: Brand::first();

        // Database Caching: Eğer yeniden üretim zorlanmamışsa ve bu videonun içerikleri veritabanında zaten varsa, API çağırma (0 Maliyet)
        if (! $force) {
            $existingGenerations = AiContentGeneration::where('video_id', $video->id)->get();
            $platformCodes = ['instagram', 'facebook', 'youtube', 'x', 'pinterest', 'tiktok', 'linkedin'];
            $allExist = true;
            $existingPlatforms = [];

            foreach ($platformCodes as $code) {
                $gen = $existingGenerations->firstWhere('platform_code', $code);
                if (! $gen || empty(trim((string) $gen->caption))) {
                    $allExist = false;
                    break;
                }
                $existingPlatforms[$code] = new GeneratedPlatformContentDTO(
                    platformCode: $code,
                    title: $gen->title,
                    hookSentence: $gen->hook_sentence,
                    caption: $gen->caption,
                    hashtags: is_array($gen->hashtags) ? $gen->hashtags : [],
                    seoKeywords: is_array($gen->seo_keywords) ? $gen->seo_keywords : [],
                    callToAction: $gen->call_to_action,
                    suggestedProductUrl: $gen->suggested_product_url,
                    isTestMode: $gen->ai_model === 'smart-library-engine'
                );
            }

            if ($allExist && count($existingPlatforms) === count($platformCodes)) {
                return new MultiPlatformContentGenerationResultDTO(
                    platforms: $existingPlatforms,
                    modelUsed: $existingGenerations->first()?->ai_model ?? $this->model,
                    promptTokens: 0,
                    completionTokens: 0,
                    costUsd: 0,
                    isTestMode: $existingGenerations->first()?->ai_model === 'smart-library-engine'
                );
            }
        }

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
        // 1. Önce veritabanında mevcut mu kontrol et (0 API çağrısı, 0 maliyet)
        $existing = AiContentGeneration::where('video_id', $video->id)
            ->where('platform_code', $platformCode)
            ->whereNotNull('caption')
            ->where('caption', '!=', '')
            ->first();

        if ($existing) {
            return [
                'title' => $existing->title,
                'hook_sentence' => $existing->hook_sentence,
                'caption' => $existing->caption,
                'hashtags' => is_array($existing->hashtags) ? $existing->hashtags : [],
                'seo_keywords' => is_array($existing->seo_keywords) ? $existing->seo_keywords : [],
                'call_to_action' => $existing->call_to_action,
                'suggested_product_url' => $existing->suggested_product_url,
            ];
        }

        // 2. Yoksa video için üret
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
                'max_tokens' => 1500,
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
                caption: "{$fbHook}\n\n{$fbBody}\n\n{$bullets}\n\nÇocuk patenli ayakkabı ve tekerlekli ayakkabı modellerini şimdi keşfet! 🛍️\n\n👉 Detaylı bilgi ve sipariş için: https://patenliayakkabilar.com\n📸 @patenliayakkabilar",
                hashtags: ['PatenliAyakkabı', 'PatenliAyakkabılar', 'ÇocukPatenliAyakkabı', 'TekerlekliAyakkabı', 'RollerShoes', 'IşıklıPatenliAyakkabı', 'PatenliSporAyakkabı', 'GizliTekerlekliAyakkabı', 'ÇocukModası', 'SporAyakkabı', 'Alışveriş', 'Trend'],
                seoKeywords: $seoKeywords,
                callToAction: "Detaylı bilgi ve sipariş için: https://patenliayakkabilar.com",
                suggestedProductUrl: 'https://patenliayakkabilar.com',
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
                hashtags: ['PatenliAyakkabı', 'TekerlekliAyakkabı', 'ÇocukPatenliAyakkabı', 'PatenliSporAyakkabı', 'RollerShoes'],
                seoKeywords: $seoKeywords,
                callToAction: 'Hemen tıkla: https://patenliayakkabilar.com',
                suggestedProductUrl: 'https://patenliayakkabilar.com',
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
                caption: "Çocuk patenli ayakkabı ve tekerlekli ayakkabı modellerimizle hareketi eğlenceye dönüştürüyoruz! ⚡\n\n{$brandName} olarak çocukların ve gençlerin severek giydiği patenli ayakkabı, ışıklı patenli ayakkabı ve gizli tekerlekli spor ayakkabı modelleri sunuyoruz. Hem günlük kullanıma uygun konforlu spor ayakkabı, hem de tek hareketle kaymaya hazır paten deneyimi bir arada!\n\n{$bullets}\n\nTüm patenli ayakkabı modelleri ve fiyatları için resmi web sitemizi ziyaret edin:\npatenliayakkabilar.com",
                hashtags: ['PatenliAyakkabı', 'TekerlekliAyakkabı', 'ÇocukPatenliAyakkabı', 'IşıklıPatenliAyakkabı', 'PatenliAyakkabılar', 'PatenliSporAyakkabı', 'ETicaret', 'Perakende', 'İnovasyon', 'Girişimcilik'],
                seoKeywords: $seoKeywords,
                callToAction: 'Patenli ayakkabı modellerini inceleyin: patenliayakkabilar.com',
                suggestedProductUrl: 'patenliayakkabilar.com',
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
Sen uzman bir Sosyal Medya ve SEO Mimarı olarak verilen video ve marka profiline uygun, özgün, yüksek dönüşümlü Türkçe içerik üreten bir AI'sın.
Marka: {$brandName} | Slogan: {$slogan} | Ton: {$brandTone} | Yasaklı Kelimeler: {$forbidden}

GENEL KURALLAR:
1. Ürün: "Patenli Ayakkabı" / "Tekerlekli Spor Ayakkabı"dır (tek tıkla patene dönüşen ayakkabılar). Asla "1. Video", "klip", dosya adı gibi iç sistem etiketlerini metne yazma.
2. SEO ve Google Sıralaması: Metin gönderilerinde temel amaç Google arama motorunda üst sıralara çıkmak ve organik SEO trafiği oluşturmaktır. Hedef anahtar kelimeleri (patenli ayakkabı, tekerlekli ayakkabı, çocuk patenli ayakkabı, ışıklı patenli ayakkabı, tekerlekli spor ayakkabı modelleri) başlık, kanca ve maddelerde zengin ve doğal bir şekilde kullan.
3. Emojiler: Video gönderilerinde sadece tüm cihazlarda sorunsuz görünen evrensel emojileri kullan (✨, 🔥, ⚡, 🎁, 👟, 🚚, 🛍️, 🌐, 📸, 👉, 🚀, ⭐). Metin gönderilerinde ise kesinlikle HİÇBİR EMOJİ KULLANMA, sade ve profesyonel bir dil kullan! Windows'ta boş kutu (tofu) çıkan paten/kaykay emojilerini asla kullanma!
4. Web Sitesi Linkleri:
   - Facebook ve X (Twitter) için: Daima "https://patenliayakkabilar.com" (tam HTTPS bağlantısı) kullan.
   - LinkedIn için: Daima "patenliayakkabilar.com" (yalın alan adı) kullan.
5. Hashtagler: Zengin ve SEO odaklı hashtagler oluştur (marka, ürün tipi, arama sorguları ve trend etiketler).
6. Caption Şablonu: Tek parça düz metin KESİNLİKLE YASAKTIR. Satır satır, boşluklu (\n\n) ve maddeli görsel bloklardan oluşmalıdır.
7. Platform Kuralları:
   - instagram: Reels odaklı samimi kanca, title: null, 5-8 hashtag.
   - facebook: title zorunlu, aile dostu açıklama, CTA ve tam link (https://patenliayakkabilar.com), 10-15 zengin hashtag.
   - youtube: title zorunlu (max 90 karakter, emojili SEO Shorts başlığı), açıklama, etiketler.
   - x: title: null, max 240 karakter, satır satır maddeli tweet şablonu, link (https://patenliayakkabilar.com), 3-5 zengin hashtag.
   - pinterest: title zorunlu (max 90 karakter), görsel SEO açıklaması.
   - tiktok: title: null, genç kitle dili, etkileşimli CTA, 4-5 hashtag.
   - linkedin: title: null, marka ve perakende vizyonu odaklı metin, link (patenliayakkabilar.com), 8-12 zengin hashtag.

ÖNEMLİ: Gereksiz uzatmalardan kaçın, özlü, vurucu ve net yaz. Çıktıyı SADECE aşağıdaki JSON formatında tek bir JSON objesi olarak üret:
{
  "instagram": {"title": null, "hook_sentence": "kanca", "caption": "metin", "hashtags": ["PatenliAyakkabı", "TekerlekliAyakkabı"], "seo_keywords": ["patenli ayakkabı"], "call_to_action": "cta", "suggested_product_url": "url"},
  "facebook": {"title": "başlık", "hook_sentence": "kanca", "caption": "metin", "hashtags": ["PatenliAyakkabı"], "seo_keywords": ["patenli ayakkabı"], "call_to_action": "cta", "suggested_product_url": "url"},
  "youtube": {"title": "başlık", "hook_sentence": "kanca", "caption": "metin", "hashtags": ["Shorts", "PatenliAyakkabı"], "seo_keywords": ["patenli ayakkabı"], "call_to_action": "cta", "suggested_product_url": "url"},
  "x": {"title": null, "hook_sentence": "kanca", "caption": "metin", "hashtags": ["PatenliAyakkabı"], "seo_keywords": ["patenli ayakkabı"], "call_to_action": "cta", "suggested_product_url": "url"},
  "pinterest": {"title": "başlık", "hook_sentence": "kanca", "caption": "metin", "hashtags": ["PatenliAyakkabı"], "seo_keywords": ["patenli ayakkabı"], "call_to_action": "cta", "suggested_product_url": "url"},
  "tiktok": {"title": null, "hook_sentence": "kanca", "caption": "metin", "hashtags": ["PatenliAyakkabı", "fyp"], "seo_keywords": ["patenli ayakkabı"], "call_to_action": "cta", "suggested_product_url": "url"},
  "linkedin": {"title": null, "hook_sentence": "kanca", "caption": "metin", "hashtags": ["PatenliAyakkabı"], "seo_keywords": ["patenli ayakkabı"], "call_to_action": "cta", "suggested_product_url": "url"}
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

    /**
     * Metin içindeki tüm emojileri, sembolleri ve piktogramları temizler (Metin postları için)
     */
    public static function stripEmojis(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Geniş kapsamlı Unicode emoji, sembol ve varyasyon seçici deseni
        $pattern = '/[\p{Extended_Pictographic}\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}\x{200D}\x{200B}]/u';
        $cleaned = preg_replace($pattern, '', $text);

        // Satır içi birden fazla boşluğu teke indir ve satır başı/sonu boşlukları temizle
        $cleaned = preg_replace('/[ \t]+/u', ' ', $cleaned);
        $cleaned = preg_replace('/^ +| +$/mu', '', $cleaned);
        // 3 veya daha fazla satır sonunu 2'ye indir
        $cleaned = preg_replace("/\n{3,}/u", "\n\n", $cleaned);

        return trim($cleaned);
    }
}
