<?php

namespace App\Services\AI\DTOs;

class GeneratedPlatformContentDTO
{
    public function __construct(
        public string $platformCode,
        public string $title,
        public string $hookSentence,
        public string $caption,
        public array $hashtags,
        public array $seoKeywords,
        public string $callToAction,
        public ?string $suggestedProductUrl = null,
        public bool $isTestMode = false
    ) {}

    public function toArray(): array
    {
        return [
            'platform_code' => $this->platformCode,
            'title' => $this->title,
            'hook_sentence' => $this->hookSentence,
            'caption' => $this->caption,
            'hashtags' => $this->hashtags,
            'seo_keywords' => $this->seoKeywords,
            'call_to_action' => $this->callToAction,
            'suggested_product_url' => $this->suggestedProductUrl,
        ];
    }
}
