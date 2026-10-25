<?php

namespace App\Services\Scheduling;

use App\Models\ScheduledPostTarget;
use App\Models\SocialAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AlgorithmicGrowthSchedulerService
{
    /** @var array<string, PlatformAlgorithmProfile> */
    protected array $profiles = [];

    public function __construct()
    {
        $this->registerProfiles();
    }

    protected function registerProfiles(): void
    {
        $this->profiles = [
            'instagram' => new PlatformAlgorithmProfile(
                platformCode: 'instagram',
                platformName: 'Instagram Reels',
                icon: 'heroicon-o-camera',
                peakSlotsWeekday: ['12:15', '17:45', '21:10'],
                peakSlotsWeekend: ['11:30', '16:00', '20:45'],
                minCooldownMinutes: 180, // 3 saat
                maxRecommendedDailyPosts: 3,
                staggerPriority: 1,
                staggerDelayMinutes: 0,
                jitterMinMinutes: 3,
                jitterMaxMinutes: 9,
                algorithmTip: 'Reels keşfet algoritması ilk 45 dakikadaki izlenme tamamlama ve kaydetme oranını test eder. 3 saatten sık paylaşım shadowban ve erişim kısıtlaması getirir.',
                bestDays: [1, 2, 3, 4, 5, 6, 7]
            ),

            'tiktok' => new PlatformAlgorithmProfile(
                platformCode: 'tiktok',
                platformName: 'TikTok FYP',
                icon: 'heroicon-o-musical-note',
                peakSlotsWeekday: ['13:00', '16:30', '20:15', '22:30'],
                peakSlotsWeekend: ['12:00', '15:30', '19:30', '23:00'],
                minCooldownMinutes: 120, // 2 saat
                maxRecommendedDailyPosts: 4,
                staggerPriority: 2,
                staggerDelayMinutes: 7,
                jitterMinMinutes: 4,
                jitterMaxMinutes: 11,
                algorithmTip: 'FYP (Sizin İçin) havuzunda ilk 300 test izleyicisine girer. 2 saatlik aralık ve 20:00-23:00 gece penceresi en yüksek viral patlamayı sağlar.',
                bestDays: [1, 2, 3, 4, 5, 6, 7]
            ),

            'youtube' => new PlatformAlgorithmProfile(
                platformCode: 'youtube',
                platformName: 'YouTube Shorts',
                icon: 'heroicon-o-play-circle',
                peakSlotsWeekday: ['14:30', '18:30', '21:15'],
                peakSlotsWeekend: ['11:00', '15:00', '19:45'],
                minCooldownMinutes: 240, // 4 saat
                maxRecommendedDailyPosts: 2,
                staggerPriority: 3,
                staggerDelayMinutes: 14,
                jitterMinMinutes: 5,
                jitterMaxMinutes: 12,
                algorithmTip: 'Shorts algoritması videoyu yüklendikten 4-6 saat sonra Shorts rafına sokar. Günde max 2 paylaşım ve 4 saat soğuma süresi spam cezasını engeller.',
                bestDays: [1, 2, 3, 4, 5, 6, 7]
            ),

            'facebook' => new PlatformAlgorithmProfile(
                platformCode: 'facebook',
                platformName: 'Facebook Reels & Akış',
                icon: 'heroicon-o-globe-alt',
                peakSlotsWeekday: ['09:45', '14:00', '20:00'],
                peakSlotsWeekend: ['10:30', '15:00', '20:30'],
                minCooldownMinutes: 240, // 4 saat
                maxRecommendedDailyPosts: 2,
                staggerPriority: 4,
                staggerDelayMinutes: 21,
                jitterMinMinutes: 4,
                jitterMaxMinutes: 10,
                algorithmTip: 'Ebeveyn kitlesi sabah 09:30-11:00 ve akşam 20:00’de en aktiftir. Çok sık post atılması sayfa kalite puanını ve akış dağıtımını düşürür.',
                bestDays: [1, 2, 3, 4, 5, 6, 7]
            ),

            'x' => new PlatformAlgorithmProfile(
                platformCode: 'x',
                platformName: 'X (Twitter)',
                icon: 'heroicon-o-chat-bubble-bottom-center-text',
                peakSlotsWeekday: ['09:00', '13:15', '17:30', '21:45'],
                peakSlotsWeekend: ['10:30', '14:00', '18:30', '22:00'],
                minCooldownMinutes: 90, // 1.5 saat
                maxRecommendedDailyPosts: 4,
                staggerPriority: 5,
                staggerDelayMinutes: 28,
                jitterMinMinutes: 3,
                jitterMaxMinutes: 8,
                algorithmTip: 'X anlık tüketim mecrasıdır. Gündem ve iş çıkışı saatlerinde organik görünürlük zirve yapar.',
                bestDays: [1, 2, 3, 4, 5, 6, 7]
            ),

            'pinterest' => new PlatformAlgorithmProfile(
                platformCode: 'pinterest',
                platformName: 'Pinterest Pins',
                icon: 'heroicon-o-bookmark',
                peakSlotsWeekday: ['15:30', '20:30', '22:45'],
                peakSlotsWeekend: ['14:00', '19:30', '22:15'],
                minCooldownMinutes: 180, // 3 saat
                maxRecommendedDailyPosts: 3,
                staggerPriority: 6,
                staggerDelayMinutes: 35,
                jitterMinMinutes: 5,
                jitterMaxMinutes: 13,
                algorithmTip: 'Görsel arama motorudur. Akşam saatlerinde pin kaydetme ve ürün kartı tıklamaları 3 katına çıkar.',
                bestDays: [5, 6, 7, 1]
            ),

            'linkedin' => new PlatformAlgorithmProfile(
                platformCode: 'linkedin',
                platformName: 'LinkedIn Post',
                icon: 'heroicon-o-briefcase',
                peakSlotsWeekday: ['08:30', '12:30', '17:00'],
                peakSlotsWeekend: ['10:30'],
                minCooldownMinutes: 360, // 6 saat
                maxRecommendedDailyPosts: 1,
                staggerPriority: 7,
                staggerDelayMinutes: 42,
                jitterMinMinutes: 4,
                jitterMaxMinutes: 10,
                algorithmTip: 'Sadece iş günlerinde organik dağıtım yapar. Hafta sonu paylaşım erişimi %70 düşer. Günde 1 kaliteli paylaşım kuralı esastır.',
                bestDays: [2, 3, 4, 5]
            ),
        ];
    }

    public function getProfile(string $platformCode): PlatformAlgorithmProfile
    {
        $code = strtolower(trim($platformCode));

        return $this->profiles[$code] ?? new PlatformAlgorithmProfile(
            platformCode: $code,
            platformName: ucfirst($code),
            icon: 'heroicon-o-share',
            peakSlotsWeekday: ['12:00', '18:00', '21:00'],
            peakSlotsWeekend: ['12:00', '17:00', '20:30'],
            minCooldownMinutes: 180,
            maxRecommendedDailyPosts: 3,
            staggerPriority: 8,
            staggerDelayMinutes: 15,
            jitterMinMinutes: 3,
            jitterMaxMinutes: 10,
            algorithmTip: 'Standart organik dağıtım saatleri.',
            bestDays: [1, 2, 3, 4, 5, 6, 7]
        );
    }

    /**
     * @return array<string, PlatformAlgorithmProfile>
     */
    public function getAllProfiles(): array
    {
        return $this->profiles;
    }

    /**
     * Algoritmik Keşfet modunda gün içi ana tetikleyici saatleri döndürür
     *
     * @param string $strategy 'conservative' (1-2 post), 'balanced' (2-3 post), 'aggressive' (3-4 post)
     * @return array<string> ['12:15', '17:45', '21:10'] gibi
     */
    public function getAlgorithmicRunTimes(string $strategy = 'balanced', ?Carbon $date = null): array
    {
        $date = $date ?: now();
        $isWeekend = $date->isWeekend();

        // Stratejiye göre günlük kaç ana algoritmik dalga üretilecek
        $slotCount = match ($strategy) {
            'conservative' => 2, // Günde 2 kez (Öğle ve Akşam zirvesi)
            'aggressive' => 4,   // Günde 4 kez (Sabah, Öğle, Akşam, Gece)
            default => 3,        // 'balanced' (Öğle, İkindi/İş Çıkışı, Akşam Prime)
        };

        // Instagram ve TikTok gibi ana video platformlarının ortak en güçlü keşfet kesişimleri
        if ($isWeekend) {
            $masterSlots = ['11:30', '15:30', '19:30', '22:15'];
        } else {
            $masterSlots = ['12:15', '17:45', '20:45', '22:30'];
        }

        return array_slice($masterSlots, 0, $slotCount);
    }

    /**
     * Bir gönderinin hedef hesapları için her platforma özel kademeli (staggered)
     * ve rastgele organik sapmalı (smart jitter) yayın zamanlarını hesaplar.
     *
     * @param iterable<SocialAccount>|array<SocialAccount> $accounts
     * @return array<int, Carbon> [social_account_id => Carbon]
     */
    public function generateStaggeredTargetTimes(
        iterable $accounts,
        Carbon $baseTime,
        bool $enableJitter = true,
        bool $enableStagger = true
    ): array {
        $accountList = $accounts instanceof Collection ? $accounts->all() : (array) $accounts;
        if (empty($accountList)) {
            return [];
        }

        // Platform önceliğine göre sırala (Instagram -> TikTok -> YouTube -> Facebook -> X -> Pinterest -> LinkedIn)
        usort($accountList, function (SocialAccount $a, SocialAccount $b) {
            $pA = $this->getProfile($a->platform?->code?->value ?? (string) $a->platform?->code);
            $pB = $this->getProfile($b->platform?->code?->value ?? (string) $b->platform?->code);
            return $pA->staggerPriority <=> $pB->staggerPriority;
        });

        $result = [];
        $cumulativeStagger = 0;

        foreach ($accountList as $index => $account) {
            $code = $account->platform?->code?->value ?? (string) $account->platform?->code;
            $profile = $this->getProfile($code);

            $targetTime = $baseTime->copy();

            // 1. Kademeli çapraz dağıtım (Staggered Cross-Posting)
            if ($enableStagger && $index > 0) {
                // Her platform arasında organik 6 ila 12 dakika arası güvenli boşluk bırak
                $step = mt_rand(6, 12);
                $cumulativeStagger += $step;
                $targetTime->addMinutes($cumulativeStagger);
            }

            // 2. Rastgele Organik Sapma (Smart Jitter) - Bot tespitini engeller
            if ($enableJitter) {
                $jitterMinutes = mt_rand($profile->jitterMinMinutes, $profile->jitterMaxMinutes);
                $jitterSeconds = mt_rand(1, 59);
                $targetTime->addMinutes($jitterMinutes)->addSeconds($jitterSeconds);
            }

            // 3. Algoritmik Soğuma (Cooldown Protection) kontrolü
            $targetTime = $this->ensureSafeCooldown($account, $targetTime, $profile);

            $result[$account->id] = $targetTime;
        }

        return $result;
    }

    /**
     * Bir hesabın son paylaşımı ile aday zaman arasındaki süreyi kontrol eder.
     * Eğer minimum soğuma süresi dolmamışsa zamanı güvenli saate öteler.
     */
    public function ensureSafeCooldown(SocialAccount $account, Carbon $candidateTime, ?PlatformAlgorithmProfile $profile = null): Carbon
    {
        $profile = $profile ?: $this->getProfile($account->platform?->code?->value ?? (string) $account->platform?->code);
        $minCooldown = $profile->minCooldownMinutes;

        // Bu hesabın en son planlanmış veya yayınlanmış hedefini bul
        $lastTarget = ScheduledPostTarget::where('social_account_id', $account->id)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $candidateTime)
            ->orderBy('scheduled_at', 'desc')
            ->first();

        if ($lastTarget && $lastTarget->scheduled_at) {
            $diffMinutes = $lastTarget->scheduled_at->diffInMinutes($candidateTime, false);
            if ($diffMinutes < $minCooldown) {
                $neededWait = $minCooldown - $diffMinutes;
                // Güvenli aralığa + rastgele birkaç dakika ekleyerek ertele
                $candidateTime->addMinutes($neededWait + mt_rand(2, 8));
            }
        }

        return $candidateTime;
    }

    /**
     * Filament paneli ve kullanıcı için platform algoritmaları özet kartları
     */
    public function getUiAlgorithmSummary(): array
    {
        $summary = [];
        $isWeekend = now()->isWeekend();

        foreach ($this->profiles as $code => $profile) {
            $slots = $profile->getPeakSlotsForDay($isWeekend);
            $summary[] = [
                'code' => $code,
                'name' => $profile->platformName,
                'icon' => $profile->icon,
                'peak_hours' => implode(' • ', $slots),
                'cooldown' => "Min {$profile->minCooldownMinutes} dk",
                'max_daily' => "Günde {$profile->maxRecommendedDailyPosts} post",
                'tip' => $profile->algorithmTip,
            ];
        }

        return $summary;
    }
}
