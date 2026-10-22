<?php

namespace App\Services\AI\DTOs;

class MultiPlatformContentGenerationResultDTO
{
    /**
     * @param array<string, GeneratedPlatformContentDTO> $platforms
     */
    public function __construct(
        public array $platforms,
        public string $modelUsed,
        public int $promptTokens,
        public int $completionTokens,
        public float $costUsd,
        public bool $isTestMode = false
    ) {}

    public function getPlatform(string $platformCode): ?GeneratedPlatformContentDTO
    {
        return $this->platforms[$platformCode] ?? null;
    }
}
