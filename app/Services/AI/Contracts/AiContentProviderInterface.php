<?php

namespace App\Services\AI\Contracts;

use App\Models\Brand;
use App\Models\Video;
use App\Services\AI\DTOs\MultiPlatformContentGenerationResultDTO;

interface AiContentProviderInterface
{
    /**
     * Generate structured content for all 7 platforms for a given video
     */
    public function generateForVideo(Video $video, ?Brand $brand = null, bool $force = false): MultiPlatformContentGenerationResultDTO;

    /**
     * Generate content for a single specific platform
     */
    public function generateForSinglePlatform(Video $video, string $platformCode, ?Brand $brand = null): array;

    /**
     * Check if AI provider has valid API credentials configured
     */
    public function isConfigured(): bool;
}
