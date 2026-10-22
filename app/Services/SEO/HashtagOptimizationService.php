<?php

namespace App\Services\SEO;

use App\Models\Hashtag;
use App\Models\KeywordLibrary;
use App\Models\Video;
use Illuminate\Support\Collection;

class HashtagOptimizationService
{
    /**
     * Pick optimized hashtags for a video tailored for a specific platform
     */
    public function getOptimizedHashtags(Video $video, string $platformCode, int $limit = 5): array
    {
        $platformMaxLimits = [
            'x' => 2,
            'pinterest' => 4,
            'tiktok' => 5,
            'linkedin' => 4,
            'youtube' => 4,
            'instagram' => 8,
            'facebook' => 4,
        ];

        $effectiveLimit = min($limit, $platformMaxLimits[$platformCode] ?? 5);

        // 1. Check custom video hashtags first
        $selected = [];
        if (! empty($video->custom_hashtags) && is_array($video->custom_hashtags)) {
            foreach ($video->custom_hashtags as $tag) {
                $cleaned = ltrim($tag, '#');
                if (! in_array($cleaned, $selected)) {
                    $selected[] = $cleaned;
                }
                if (count($selected) >= $effectiveLimit) {
                    return $selected;
                }
            }
        }

        // 2. Fetch active and non-blacklisted hashtags matching platform filter or global
        $query = Hashtag::where('is_active', true)
            ->where('is_blacklisted', false)
            ->where(function ($q) use ($platformCode) {
                $q->whereNull('platform_filter')
                  ->orWhere('platform_filter', $platformCode);
            })
            ->orderBy('priority', 'desc')
            ->orderBy('usage_count', 'asc') // favor fresh ones
            ->limit($effectiveLimit * 2);

        $pool = $query->get();

        foreach ($pool as $hashtag) {
            $cleaned = ltrim($hashtag->tag, '#');
            if (! in_array($cleaned, $selected)) {
                $selected[] = $cleaned;
            }

            if (count($selected) >= $effectiveLimit) {
                break;
            }
        }

        return $selected;
    }

    /**
     * Pick top SEO keywords from library for a video/brand
     */
    public function getRelevantKeywords(Video $video, ?string $platformCode = null, int $limit = 4): array
    {
        $query = KeywordLibrary::where('is_active', true)
            ->where('workspace_id', $video->workspace_id);

        if ($platformCode) {
            $query->where(function ($q) use ($platformCode) {
                $q->whereNull('platform')
                  ->orWhere('platform', $platformCode);
            });
        }

        return $query->orderBy('priority', 'desc')
            ->limit($limit)
            ->pluck('keyword')
            ->toArray();
    }

    /**
     * Record usage for hashtags
     */
    public function recordHashtagUsage(array $tags, ?int $workspaceId = 1): void
    {
        foreach ($tags as $rawTag) {
            $tag = ltrim(trim($rawTag), '#');
            if (empty($tag)) {
                continue;
            }

            Hashtag::where('tag', $tag)
                ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
                ->increment('usage_count', 1, ['last_used_at' => now()]);
        }
    }
}
