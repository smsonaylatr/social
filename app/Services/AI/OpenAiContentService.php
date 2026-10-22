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
        $this->apiKey = env('OPENAI_API_KEY') ?: Setting::get('openai_api_key');
        $this->model = Setting::get('openai_model', 'gpt-4o-mini');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && ! str_starts_with($this->apiKey, 'mock_');
    }

    /**
     * Generate content for all 7 platforms for a video
     */
    public function generateForVideo(Video $video, ?Brand $brand = null): MultiPlatformContentGenerationResultDTO
    {
        $brand = $brand ?: $video->brand ?: Brand::first();

        if (! $this->isConfigured()) {
            return $this->generateTestModeContent($video, $brand);
        }

        try {
            return $this->callOpenAiApi($video, $brand);
        } catch (\Throwable $e) {
            Log::error('OpenAI API call failed, falling back to test mode: ' . $e->getMessage());
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
            $platforms[$code] = new GeneratedPlatformContentDTO(
                platformCode: $code,
                title: $pData['title'] ?? $video->title,
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
                    'status' => 'draft',
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
     * Fallback / Test mode structured content generator
     */
    protected function generateTestModeContent(Video $video, ?Brand $brand, bool $isFallback = false, ?string $fallbackReason = null): MultiPlatformContentGenerationResultDTO
    {
        $brandName = $brand?->name ?? 'Patenli Ayakkabılar';
        $slogan = $brand?->slogan ?? 'Walk. Roll. Fly.';
        $productUrl = $video->product_url ?: ($brand?->website ?? 'https://patenliayakkabilar.com');
        $defaultCta = $brand?->default_cta ?: 'Hemen sitemizi ziyaret edin ve avantajları kaçırmayın!';

        $seoKeywords = $brand?->seo_keywords ?? [
            'patenli ayakkabı', 'tekerlekli ayakkabı', 'çocuk patenli ayakkabı', 'ışıklı patenli ayakkabı'
        ];

        $platforms = [
            'instagram' => new GeneratedPlatformContentDTO(
                platformCode: 'instagram',
                title: "{$video->title} - {$slogan}",
                hookSentence: 'Düz yolda yürürken anında kaymaya başlamak nasıl bir his? 🛼👟',
                caption: "Her adımında özgürlüğü hisset! 🌟 {$video->title} ile yürümek artık çok daha eğlenceli. Hem şık spor ayakkabı, hem tek tıkla paten! Çocuklar ve paten tutkunları için benzersiz bir deneyim.\n\n{$defaultCta}",
                hashtags: ['patenliayakkabi', 'reelskesfet', 'tekerlekliayakkabi', 'cocukmodasi', 'isikliayakkabi', 'walkrollfly'],
                seoKeywords: $seoKeywords,
                callToAction: 'Profildeki linkten hemen sipariş verin! 📲',
                suggestedProductUrl: $productUrl,
                isTestMode: true
            ),
            'facebook' => new GeneratedPlatformContentDTO(
                platformCode: 'facebook',
                title: "{$video->title} - {$brandName}",
                hookSentence: 'Çocuklarınız için hem hareketli hem de güvenli bir ayakkabı mı arıyorsunuz?',
                caption: "Tekerlekli ve patenli ayakkabılarımız ile çocuklarınızı ekran başından kaldırın! {$video->title} modelimiz gizlenebilir tekerlek sistemi, dayanıklı taban ve ortopedik tasarımıyla güvenli sürüş sağlar. Ücretsiz kargo ve kolay değişim garantisiyle kapınızda.\n\n👉 Detaylı bilgi ve sipariş için: {$productUrl}",
                hashtags: ['patenliayakkabi', 'cocukgelisimi', 'hediyefikri', 'aile'],
                seoKeywords: $seoKeywords,
                callToAction: 'Web sitemizden güvenle sipariş oluşturun.',
                suggestedProductUrl: $productUrl,
                isTestMode: true
            ),
            'youtube' => new GeneratedPlatformContentDTO(
                platformCode: 'youtube',
                title: "{$video->title} #Shorts | Patenli Ayakkabılar",
                hookSentence: 'Yürürken bir anda patene dönüşen ayakkabı incelemesi!',
                caption: "Patenli ayakkabılar nasıl kullanılır? {$video->title} modelinin kullanımını ve eğlenceli anlarını inceleyin. #Shorts formatında en pratik ipuçları.\n\n🌐 Resmi Web Sitesi: {$productUrl}\nAbone olmayı ve videoyu beğenmeyi unutmayın!",
                hashtags: ['Shorts', 'patenliayakkabi', 'rollershoes', 'paten'],
                seoKeywords: $seoKeywords,
                callToAction: 'Kanalımıza abone olun ve yeni modelleri ilk siz keşfedin!',
                suggestedProductUrl: $productUrl,
                isTestMode: true
            ),
            'x' => new GeneratedPlatformContentDTO(
                platformCode: 'x',
                title: "{$video->title}",
                hookSentence: 'Walk. Roll. Fly. 🛼',
                caption: "Yürümekten sıkılanlar için: {$video->title}! Tek bir dokunuşla tekerlekleri açın ve akmaya başlayın. Sınırlı stoklarla: {$productUrl} #patenliayakkabi",
                hashtags: ['patenliayakkabi', 'rollershoes'],
                seoKeywords: array_slice($seoKeywords, 0, 2),
                callToAction: "Hemen tıkla: {$productUrl}",
                suggestedProductUrl: $productUrl,
                isTestMode: true
            ),
            'pinterest' => new GeneratedPlatformContentDTO(
                platformCode: 'pinterest',
                title: "En Popüler {$video->title} Modelleri",
                hookSentence: 'Çocuklar ve gençler için en havalı patenli spor ayakkabı kombinleri.',
                caption: "Hem günlük spor ayakkabı hem de tekerlekli paten olarak kullanılabilen harika bir tasarım. Doğum günü hediyesi ve eğlenceli aktiviteler için ideal. Renk seçeneklerini keşfetmek için tıklayın.",
                hashtags: ['patenliayakkabi', 'cocukhediyesi', 'dogumgunuhediyesi', 'modafikirleri'],
                seoKeywords: $seoKeywords,
                callToAction: 'Ürünü İncele ve Satın Al',
                suggestedProductUrl: $productUrl,
                isTestMode: true
            ),
            'tiktok' => new GeneratedPlatformContentDTO(
                platformCode: 'tiktok',
                title: "{$video->title} ile sokaklardayız! 🛹",
                hookSentence: 'Sen olsan bu ayakkabıyla nereye kayardın? Yorumlara yaz! 👇',
                caption: "Tekerlekler gizlendiğinde spor ayakkabı, açıldığında paten! {$video->title} akımı başladı 🛼✨ #patenliayakkabi #foryou #fyp #kesfet #rollershoes",
                hashtags: ['fyp', 'kesfet', 'patenliayakkabi', 'rollershoes', 'foryoupage'],
                seoKeywords: $seoKeywords,
                callToAction: 'Takip etmeyi ve videoyu kaydetmeyi unutma!',
                suggestedProductUrl: $productUrl,
                isTestMode: true
            ),
            'linkedin' => new GeneratedPlatformContentDTO(
                platformCode: 'linkedin',
                title: "{$brandName}: Yenilikçi E-Ticaret ve Çocuk Modasında Hareket Odaklı Ürünler",
                hookSentence: 'Fiziksel aktiviteyi eğlenceli bir deneyime dönüştüren yeni nesil tekerlekli ayakkabı tasarımlarımız.',
                caption: "{$brandName} olarak genç neslin hareket kabiliyetini ve spor sevgisini artıran inovatif ayakkabı çözümleri sunuyoruz. {$video->title} serimiz ergonomik taban yapısı ve gizlenebilir mekanizması ile sektörde fark yaratmaya devam ediyor.",
                hashtags: ['inovasyon', 'eticaret', 'perakende', 'girisimcilik'],
                seoKeywords: ['e-ticaret', 'ürün inovasyonu', 'patenli ayakkabı'],
                callToAction: 'Ürün geliştirme ve kurumsal çözümlerimiz için web sitemizi ziyaret edin.',
                suggestedProductUrl: $productUrl,
                isTestMode: true
            ),
        ];

        // Persist to database with draft status
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
                    'ai_model' => 'test-mode-generator',
                    'prompt_tokens' => 0,
                    'completion_tokens' => 0,
                    'cost_usd' => 0,
                    'status' => 'draft',
                ]
            );
        }

        return new MultiPlatformContentGenerationResultDTO(
            platforms: $platforms,
            modelUsed: 'test-mode-generator',
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
1. instagram (Reels odaklı, samimi, etkileyici kanca, emojiler, ilgili hashtagler)
2. facebook (Fayda odaklı, aile ve ebeveyn dostu, güven verici, bağlantı yönlendirmesi)
3. youtube (Shorts ve arama odaklı SEO başlığı, detaylı açıklama, etiketler)
4. x (Twitter: 280 karakteri aşmayan dinamik, çarpıcı metin)
5. pinterest (Arama niyetine uygun Pin başlığı, SEO açıklaması)
6. tiktok (Trendlere uygun, genç kitle dili, izleyici etkileşimi, CTA)
7. linkedin (Marka ve perakende vizyonu, profesyonel ama samimi ton)

Marka Bilgileri:
- Marka: {$brandName}
- Slogan: {$slogan}
- Ton: {$brandTone}
- Kesinlikle Yasaklı Kelimeler: {$forbidden}

Çıktıyı kesinlikle aşağıdaki JSON şemasına uygun tek bir JSON objesi olarak üret:
{
  "instagram": { "title": "...", "hook_sentence": "...", "caption": "...", "hashtags": ["..."], "seo_keywords": ["..."], "call_to_action": "...", "suggested_product_url": "..." },
  "facebook": { "title": "...", "hook_sentence": "...", "caption": "...", "hashtags": ["..."], "seo_keywords": ["..."], "call_to_action": "...", "suggested_product_url": "..." },
  "youtube": { "title": "...", "hook_sentence": "...", "caption": "...", "hashtags": ["..."], "seo_keywords": ["..."], "call_to_action": "...", "suggested_product_url": "..." },
  "x": { "title": "...", "hook_sentence": "...", "caption": "...", "hashtags": ["..."], "seo_keywords": ["..."], "call_to_action": "...", "suggested_product_url": "..." },
  "pinterest": { "title": "...", "hook_sentence": "...", "caption": "...", "hashtags": ["..."], "seo_keywords": ["..."], "call_to_action": "...", "suggested_product_url": "..." },
  "tiktok": { "title": "...", "hook_sentence": "...", "caption": "...", "hashtags": ["..."], "seo_keywords": ["..."], "call_to_action": "...", "suggested_product_url": "..." },
  "linkedin": { "title": "...", "hook_sentence": "...", "caption": "...", "hashtags": ["..."], "seo_keywords": ["..."], "call_to_action": "...", "suggested_product_url": "..." }
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
