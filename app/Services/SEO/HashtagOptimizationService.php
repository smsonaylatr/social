<?php

namespace App\Services\SEO;

use App\Enums\SocialPlatformCode;
use App\Models\Hashtag;
use App\Models\KeywordLibrary;
use App\Models\Video;
use Illuminate\Support\Collection;

class HashtagOptimizationService
{
    /**
     * Platformun izin verdiği maksimum hashtag sayısı (5-10 arası, varsayılan 10)
     */
    public function getPlatformMaxHashtagLimit(string $platformCode): int
    {
        $platformEnum = SocialPlatformCode::tryFrom(strtolower($platformCode));
        if ($platformEnum) {
            return $platformEnum->maxHashtags();
        }

        return 10;
    }

    /**
     * Pick optimized hashtags for a video tailored for a specific platform.
     * Kural: Her platform 5-10 arasında hashtag kullanır:
     * - 5 Tane SEO Anahtar Kelimelerinden türetilen hashtagler
     * - 5 Tane Keşfete çıkaracak trend / viral hashtagler
     */
    public function getOptimizedHashtags(Video $video, string $platformCode, ?int $limit = null, bool $markAsUsed = true): array
    {
        $maxLimit = $this->getPlatformMaxHashtagLimit($platformCode);
        $effectiveLimit = ($limit !== null && $limit > 0) ? min($limit, $maxLimit) : $maxLimit;
        // Limit 5 ile 10 arasında tutulur (varsayılan 10)
        $effectiveLimit = max(5, min(10, $effectiveLimit));

        $blacklistedTags = Hashtag::where('is_blacklisted', true)->pluck('tag')->map(fn ($t) => mb_strtolower($t, 'UTF-8'))->toArray();

        // ══════════════════════════════════════════════════════════════════════════
        // 1. AŞAMA: 5 TANE ANAHTAR KELİMEYE DAYALI HASHTAG (SEO Hafızası)
        // ══════════════════════════════════════════════════════════════════════════
        $keywordTarget = (int) ceil($effectiveLimit / 2); // 10 için 5 adet
        $keywordHashtags = [];

        // 1.a Varsa videoya özel tanımlanmış custom hashtag'leri başa ekle
        if (! empty($video->custom_hashtags) && is_array($video->custom_hashtags)) {
            foreach ($video->custom_hashtags as $tag) {
                $cleaned = ltrim(trim((string) $tag), '#');
                $lower = mb_strtolower($cleaned, 'UTF-8');
                if ($cleaned !== '' && ! in_array($lower, $blacklistedTags) && ! in_array($cleaned, $keywordHashtags)) {
                    $keywordHashtags[] = $cleaned;
                }
                if (count($keywordHashtags) >= $keywordTarget) {
                    break;
                }
            }
        }

        // 1.b SEO Anahtar Kelime Kütüphanesinden aktif kelimeleri çek ve PascalCase hashtag yap
        $neededKeywords = $keywordTarget - count($keywordHashtags);
        if ($neededKeywords > 0) {
            $seoKeywords = $this->getRelevantKeywords($video, $platformCode, limit: $neededKeywords + 5, markAsUsed: false);
            foreach ($seoKeywords as $keyword) {
                // "çocuk patenli ayakkabı" -> "ÇocukPatenliAyakkabı"
                $formattedTag = preg_replace('/[^\p{L}\p{N}]/u', '', mb_convert_case($keyword, MB_CASE_TITLE, 'UTF-8'));
                $lower = mb_strtolower($formattedTag, 'UTF-8');
                if ($formattedTag !== '' && ! in_array($lower, $blacklistedTags) && ! in_array($formattedTag, $keywordHashtags)) {
                    $keywordHashtags[] = $formattedTag;
                }
                if (count($keywordHashtags) >= $keywordTarget) {
                    break;
                }
            }
        }

        // ══════════════════════════════════════════════════════════════════════════
        // 2. AŞAMA: 5 TANE KEŞFETE ÇIKARACAK HASHTAG (Trending / Viral / Keşfet)
        // ══════════════════════════════════════════════════════════════════════════
        $exploreTarget = $effectiveLimit - count($keywordHashtags); // 10 için 5 adet
        $exploreHashtags = [];
        $existingLower = array_map(fn ($t) => mb_strtolower($t, 'UTF-8'), $keywordHashtags);

        // Hashtag havuzundan aktif, kara listede olmayan ve öncelikle trending kategorisindeki etiketleri çek
        $pool = Hashtag::where('is_active', true)
            ->where('is_blacklisted', false)
            ->where(function ($q) use ($platformCode) {
                $q->whereNull('platform_filter')
                  ->orWhere('platform_filter', $platformCode);
            })
            ->when($video->workspace_id, fn ($q) => $q->where('workspace_id', $video->workspace_id))
            ->orderByRaw("CASE WHEN category = 'trending' THEN 0 ELSE 1 END ASC")
            ->orderBy('usage_count', 'asc') // En az kullanılanlar
            ->orderByRaw('last_used_at IS NULL DESC, last_used_at ASC') // En eski / hiç kullanılmayanlar
            ->orderBy('priority', 'desc')
            ->limit(max($exploreTarget * 5, 50))
            ->get();

        // Çeşitlilik ve sürekli rotasyon için grupları rastgele harmanla (shuffle)
        $grouped = $pool->groupBy(fn ($item) => "{$item->category}_{$item->usage_count}_{$item->priority}");
        $shuffledPool = collect();
        foreach ($grouped as $group) {
            $shuffledPool = $shuffledPool->concat($group->shuffle());
        }

        foreach ($shuffledPool as $hashtag) {
            $cleaned = ltrim(trim((string) $hashtag->tag), '#');
            $lower = mb_strtolower($cleaned, 'UTF-8');
            if ($cleaned !== '' && ! in_array($lower, $existingLower) && ! in_array($lower, $blacklistedTags) && ! in_array($cleaned, $exploreHashtags)) {
                $exploreHashtags[] = $cleaned;
                $existingLower[] = $lower;
            }
            if (count($exploreHashtags) >= $exploreTarget) {
                break;
            }
        }

        // Keşfet havuzunda eksik kalırsa yüksek etkileşimli evrensel keşfet etiketleri ile tamamla
        $fallbackExplore = ['kesfet', 'fyp', 'viral', 'trend', 'kesfetteyiz', 'reels', 'shorts', 'onecikar', 'kesfetbeni', 'tiktoktrend'];
        if (count($exploreHashtags) < $exploreTarget) {
            foreach ($fallbackExplore as $tag) {
                $lower = mb_strtolower($tag, 'UTF-8');
                if (! in_array($lower, $existingLower) && ! in_array($lower, $blacklistedTags) && ! in_array($tag, $exploreHashtags)) {
                    $exploreHashtags[] = $tag;
                    $existingLower[] = $lower;
                }
                if (count($exploreHashtags) >= $exploreTarget) {
                    break;
                }
            }
        }

        // ══════════════════════════════════════════════════════════════════════════
        // 3. AŞAMA: 5 ANAHTAR KELİME + 5 KEŞFET ETİKETİNİ BİRLEŞTİR
        // ══════════════════════════════════════════════════════════════════════════
        $selected = array_merge($keywordHashtags, $exploreHashtags);

        // Kullanılan hashtag'lerin kullanım istatistiklerini güncelle (rotasyon motoru)
        if ($markAsUsed && ! empty($selected)) {
            $this->recordHashtagUsage($selected, $video->workspace_id);
        }

        return $selected;
    }

    /**
     * Pick top SEO keywords from library for a video/brand with smart rotation
     * SEO Anahtar Kelime hafızasından kullanım sıklığına ve son kullanımına göre rotasyonlu çeker.
     */
    public function getRelevantKeywords(Video $video, ?string $platformCode = null, int $limit = 4, bool $markAsUsed = false): array
    {
        $workspaceId = $video->workspace_id ?: ($video->brand?->workspace_id ?: 1);

        $query = KeywordLibrary::where('is_active', true)
            ->when($workspaceId, function ($q) use ($workspaceId) {
                $q->where(function ($sub) use ($workspaceId) {
                    $sub->where('workspace_id', $workspaceId)
                        ->orWhereNull('workspace_id');
                });
            });

        if ($platformCode) {
            $query->where(function ($q) use ($platformCode) {
                $q->whereNull('platform')
                  ->orWhere('platform', $platformCode);
            });
        }

        // Rotasyon motoru: En az kullanılanlar ve en eski/hiç kullanılmamış olanlar öne gelir
        $pool = $query->orderBy('usage_count', 'asc')
            ->orderByRaw('last_used_at IS NULL DESC, last_used_at ASC')
            ->orderBy('priority', 'desc')
            ->limit(max($limit * 3, 30))
            ->get();

        // Eşit kullanım grubundaki kelimeleri karıştırarak çeşitlilik sağla
        $grouped = $pool->groupBy(fn ($item) => "{$item->usage_count}_{$item->priority}");
        $shuffledPool = collect();
        foreach ($grouped as $group) {
            $shuffledPool = $shuffledPool->concat($group->shuffle());
        }

        $selected = [];
        foreach ($shuffledPool as $item) {
            $kw = trim((string) $item->keyword);
            if ($kw !== '' && ! in_array($kw, $selected)) {
                $selected[] = $kw;
            }
            if (count($selected) >= $limit) {
                break;
            }
        }

        // Eğer kütüphanedeki anahtar kelime sayısı limitin altındaysa marka SEO kelimeleriyle destekle
        if (count($selected) < $limit) {
            $brandKeywords = $video->brand?->seo_keywords ?? [
                'patenli ayakkabı', 'tekerlekli ayakkabı', 'çocuk patenli ayakkabı', 'ışıklı patenli ayakkabı', 'patenli spor ayakkabı'
            ];
            foreach ($brandKeywords as $bKw) {
                $cleaned = trim((string) $bKw);
                if ($cleaned !== '' && ! in_array($cleaned, $selected)) {
                    $selected[] = $cleaned;
                }
                if (count($selected) >= $limit) {
                    break;
                }
            }
        }

        if ($markAsUsed && ! empty($selected)) {
            $this->recordKeywordUsage($selected, $workspaceId);
        }

        return $selected;
    }

    /**
     * Record usage for hashtags - kullanım sayısını artırır ve son kullanım tarihini günceller
     */
    public function recordHashtagUsage(array $tags, ?int $workspaceId = 1): void
    {
        $cleanTags = [];
        foreach ($tags as $rawTag) {
            $clean = ltrim(trim((string) $rawTag), '#');
            if ($clean !== '') {
                $cleanTags[] = $clean;
            }
        }

        if (empty($cleanTags)) {
            return;
        }

        Hashtag::whereIn('tag', $cleanTags)
            ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->increment('usage_count', 1, ['last_used_at' => now()]);
    }

    /**
     * Record usage for SEO keywords - anahtar kelimelerin kullanım sayısını ve son kullanımını günceller
     */
    public function recordKeywordUsage(array $keywords, ?int $workspaceId = 1): void
    {
        $cleanKeywords = [];
        foreach ($keywords as $rawKw) {
            $cleaned = trim((string) $rawKw);
            if ($cleaned !== '') {
                $cleanKeywords[] = mb_strtolower($cleaned, 'UTF-8');
            }
        }

        if (empty($cleanKeywords)) {
            return;
        }

        $records = KeywordLibrary::where('is_active', true)
            ->when($workspaceId, function ($q) use ($workspaceId) {
                $q->where(function ($sub) use ($workspaceId) {
                    $sub->where('workspace_id', $workspaceId)
                        ->orWhereNull('workspace_id');
                });
            })
            ->get();

        $idsToUpdate = [];
        foreach ($records as $record) {
            if (in_array(mb_strtolower(trim($record->keyword), 'UTF-8'), $cleanKeywords)) {
                $idsToUpdate[] = $record->id;
            }
        }

        if (! empty($idsToUpdate)) {
            KeywordLibrary::whereIn('id', $idsToUpdate)
                ->increment('usage_count', 1, ['last_used_at' => now()]);
        }
    }

    /**
     * Gönderi içeriğinde geçen anahtar kelimeleri tespit eder ve hafızada kullanım kaydı oluşturur
     */
    public function recordKeywordUsageFromContent(string $content, ?int $workspaceId = 1): void
    {
        if (trim($content) === '') {
            return;
        }

        $records = KeywordLibrary::where('is_active', true)
            ->when($workspaceId, function ($q) use ($workspaceId) {
                $q->where(function ($sub) use ($workspaceId) {
                    $sub->where('workspace_id', $workspaceId)
                        ->orWhereNull('workspace_id');
                });
            })
            ->get();

        $matchedIds = [];
        foreach ($records as $record) {
            $kw = trim($record->keyword);
            if ($kw !== '' && mb_stripos($content, $kw) !== false) {
                $matchedIds[] = $record->id;
            }
        }

        if (! empty($matchedIds)) {
            KeywordLibrary::whereIn('id', $matchedIds)
                ->increment('usage_count', 1, ['last_used_at' => now()]);
        }
    }
}
