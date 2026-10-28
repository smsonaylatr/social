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
     * Kural: Sadece 5 Marka ('brand') ve 5 Trend ('trending') kategorisinden olmak üzere toplam 10 hashtag seçilir.
     * Her iki havuz da kullanım sıklığına ve son kullanımına göre sürekli rotasyonla döner.
     */
    public function getOptimizedHashtags(Video $video, string $platformCode, ?int $limit = null, bool $markAsUsed = true): array
    {
        $platformCode = strtolower(trim($platformCode));
        $maxLimit = $this->getPlatformMaxHashtagLimit($platformCode);
        $effectiveLimit = ($limit !== null && $limit > 0) ? $limit : $maxLimit;
        $effectiveLimit = max(1, min(15, $effectiveLimit));

        $blacklistedTags = Hashtag::where('is_blacklisted', true)->pluck('tag')->map(fn ($t) => mb_strtolower($t, 'UTF-8'))->toArray();
        // Güvenlik: Eksik/yarım kalan veya istenmeyen etiketleri kara listeye dahil et
        $blacklistedTags = array_merge($blacklistedTags, ['pat', 'pa', 'p', 'video', 'klip']);

        // ══════════════════════════════════════════════════════════════════════════
        // 1. AŞAMA: MARKA HASHTAG'LERİ (category = 'brand')
        // ══════════════════════════════════════════════════════════════════════════
        $brandTarget = (int) ceil($effectiveLimit / 2);
        $brandHashtags = [];

        // 1.a Varsa videoya özel custom hashtag'lerden marka kategorisine uyanları al
        if (! empty($video->custom_hashtags) && is_array($video->custom_hashtags)) {
            foreach ($video->custom_hashtags as $tag) {
                $cleaned = $this->normalizeTag((string) $tag);
                if ($this->isTagValidForPlatform($cleaned, $platformCode, $blacklistedTags) && ! in_array($cleaned, $brandHashtags)) {
                    $brandHashtags[] = $cleaned;
                }
                if (count($brandHashtags) >= $brandTarget) {
                    break;
                }
            }
        }

        // 1.b 'brand' kategorisindeki aktif hashtag havuzundan en az ve en eski kullanılanları çek
        $neededBrand = $brandTarget - count($brandHashtags);
        if ($neededBrand > 0) {
            $brandPool = Hashtag::where('is_active', true)
                ->where('is_blacklisted', false)
                ->where('category', 'brand')
                ->where(function ($q) use ($platformCode) {
                    $q->whereNull('platform_filter')
                      ->orWhere('platform_filter', $platformCode);
                })
                ->when($video->workspace_id, fn ($q) => $q->where('workspace_id', $video->workspace_id))
                ->orderBy('usage_count', 'asc') // En az kullanılanlar
                ->orderByRaw('last_used_at IS NULL DESC, last_used_at ASC') // En eski kullanılanlar
                ->orderBy('priority', 'desc')
                ->limit(max($neededBrand * 4, 30))
                ->get();

            // Rotasyon için eşit kullanım grubunu rastgele harmanla
            $groupedBrand = $brandPool->groupBy(fn ($item) => "{$item->usage_count}_{$item->priority}");
            $shuffledBrandPool = collect();
            foreach ($groupedBrand as $group) {
                $shuffledBrandPool = $shuffledBrandPool->concat($group->shuffle());
            }

            foreach ($shuffledPool = $shuffledBrandPool as $hashtag) {
                $cleaned = $this->normalizeTag((string) $hashtag->tag);
                if ($this->isTagValidForPlatform($cleaned, $platformCode, $blacklistedTags) && ! in_array($cleaned, $brandHashtags)) {
                    $brandHashtags[] = $cleaned;
                }
                if (count($brandHashtags) >= $brandTarget) {
                    break;
                }
            }
        }

        // 1.c Eğer marka havuzunda eksik kalırsa varsayılan marka etiketleriyle tamamla
        $fallbackBrandTags = ['patenliayakkabilar', 'patenliayakkabi', 'walkrollfly', 'tekerleklisporayakkabi', 'cocukpatenliayakkabi', 'ayakkabipaten'];
        if (count($brandHashtags) < $brandTarget) {
            foreach ($fallbackBrandTags as $tag) {
                $cleaned = $this->normalizeTag($tag);
                if ($this->isTagValidForPlatform($cleaned, $platformCode, $blacklistedTags) && ! in_array($cleaned, $brandHashtags)) {
                    $brandHashtags[] = $cleaned;
                }
                if (count($brandHashtags) >= $brandTarget) {
                    break;
                }
            }
        }

        // ══════════════════════════════════════════════════════════════════════════
        // 2. AŞAMA: TREND HASHTAG'LERİ (category = 'trending')
        // ══════════════════════════════════════════════════════════════════════════
        $trendTarget = max(0, $effectiveLimit - count($brandHashtags));
        $trendHashtags = [];
        $existingLower = array_map(fn ($t) => mb_strtolower($t, 'UTF-8'), $brandHashtags);

        if ($trendTarget > 0) {
            // 'trending' kategorisindeki aktif hashtag havuzundan çek
            $trendPool = Hashtag::where('is_active', true)
                ->where('is_blacklisted', false)
                ->where('category', 'trending')
                ->where(function ($q) use ($platformCode) {
                    $q->whereNull('platform_filter')
                      ->orWhere('platform_filter', $platformCode);
                })
                ->when($video->workspace_id, fn ($q) => $q->where('workspace_id', $video->workspace_id))
                ->orderBy('usage_count', 'asc') // En az kullanılanlar
                ->orderByRaw('last_used_at IS NULL DESC, last_used_at ASC') // En eski / hiç kullanılmayanlar
                ->orderBy('priority', 'desc')
                ->limit(max($trendTarget * 4, 40))
                ->get();

            // Rotasyon için eşit kullanım grubunu rastgele harmanla
            $groupedTrend = $trendPool->groupBy(fn ($item) => "{$item->usage_count}_{$item->priority}");
            $shuffledTrendPool = collect();
            foreach ($groupedTrend as $group) {
                $shuffledTrendPool = $shuffledTrendPool->concat($group->shuffle());
            }

            foreach ($shuffledTrendPool as $hashtag) {
                $cleaned = $this->normalizeTag((string) $hashtag->tag);
                $lower = mb_strtolower($cleaned, 'UTF-8');
                if ($this->isTagValidForPlatform($cleaned, $platformCode, $blacklistedTags) && ! in_array($lower, $existingLower) && ! in_array($cleaned, $trendHashtags)) {
                    $trendHashtags[] = $cleaned;
                    $existingLower[] = $lower;
                }
                if (count($trendHashtags) >= $trendTarget) {
                    break;
                }
            }

            // Eğer trend havuzunda eksik kalırsa platforma özel garantili trend etiketleriyle tamamla
            $fallbackTrendTags = match ($platformCode) {
                'x' => ['trend', 'viral', 'kesfet', 'paten', 'spor'],
                'instagram' => ['kesfet', 'reels', 'viral', 'instareels', 'trend'],
                'youtube' => ['shorts', 'shortsvideo', 'trend', 'viral', 'kesfet'],
                'tiktok' => ['fyp', 'kesfet', 'kesfetteyiz', 'viral', 'trend'],
                'pinterest' => ['trend', 'kesfet', 'hediyefikri', 'alisveris'],
                'linkedin' => ['trend', 'girisimcilik', 'inovasyon', 'eticaret'],
                default => ['kesfet', 'viral', 'trend', 'onecikar'],
            };

            if (count($trendHashtags) < $trendTarget) {
                foreach ($fallbackTrendTags as $tag) {
                    $cleaned = $this->normalizeTag($tag);
                    $lower = mb_strtolower($cleaned, 'UTF-8');
                    if ($this->isTagValidForPlatform($cleaned, $platformCode, $blacklistedTags) && ! in_array($lower, $existingLower) && ! in_array($cleaned, $trendHashtags)) {
                        $trendHashtags[] = $cleaned;
                        $existingLower[] = $lower;
                    }
                    if (count($trendHashtags) >= $trendTarget) {
                        break;
                    }
                }
            }
        }

        // ══════════════════════════════════════════════════════════════════════════
        // 3. AŞAMA: MARKA + TREND HASHTAG'LERİNİ BİRLEŞTİR
        // ══════════════════════════════════════════════════════════════════════════
        $selected = array_merge($brandHashtags, $trendHashtags);

        // Kullanılan hashtag'lerin kullanım istatistiklerini güncelle (rotasyon motoru)
        if ($markAsUsed && ! empty($selected)) {
            $this->recordHashtagUsage($selected, $video->workspace_id);
        }

        return $selected;
    }

    /**
     * Etiketi temizler, boşlukları ve geçersiz simgeleri kaldırır
     */
    public function normalizeTag(string $rawTag): string
    {
        $tag = ltrim(trim($rawTag), '#');
        $tag = str_replace(' ', '', $tag);
        // Türkçe karakterleri ve alfanümerik karakterleri koru
        $tag = preg_replace('/[^\p{L}\p{N}_]/u', '', $tag);
        return $tag;
    }

    /**
     * Etiketin hedef platform için uygun olup olmadığını denetler (Platform Çapraz Bulaşma Koruması)
     */
    public function isTagValidForPlatform(string $tag, string $platformCode, array $blacklistedTags = []): bool
    {
        $lower = mb_strtolower(trim($tag), 'UTF-8');

        // Boş veya 2 karakterden kısa veya kara listede olan etiketler geçersizdir
        if (mb_strlen($lower, 'UTF-8') < 3 || in_array($lower, $blacklistedTags)) {
            return false;
        }

        $platformCode = strtolower($platformCode);

        // 1. Instagram etiketleri (reels, instareels vb.) diğer platformlarda kullanılamaz
        if (str_contains($lower, 'reel') || str_contains($lower, 'insta')) {
            if ($platformCode !== 'instagram' && $platformCode !== 'facebook') {
                return false;
            }
        }

        // 2. YouTube Shorts etiketleri diğer platformlarda kullanılamaz
        if (str_contains($lower, 'short')) {
            if ($platformCode !== 'youtube') {
                return false;
            }
        }

        // 3. TikTok etiketleri (fyp, viraltiktok, foryou vb.) diğer platformlarda kullanılamaz
        if (str_contains($lower, 'tiktok') || str_contains($lower, 'fyp') || str_contains($lower, 'foryou') || str_contains($lower, 'akim')) {
            if ($platformCode !== 'tiktok') {
                return false;
            }
        }

        return true;
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
