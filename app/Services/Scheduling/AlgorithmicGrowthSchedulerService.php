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
                peakSlotsWeekday: ['09:30', '12:45', '16:15', '19:15', '22:00'],
                peakSlotsWeekend: ['10:30', '13:15', '16:30', '19:45', '22:30'],
                minCooldownMinutes: 90, // 1.5 saat
                maxRecommendedDailyPosts: 5,
                staggerPriority: 1,
                staggerDelayMinutes: 0,
                jitterMinMinutes: 3,
                jitterMaxMinutes: 8,
                algorithmTip: 'Reels algoritması ilk 45 dakikadaki izlenme tamamlama ve kaydetme oranını test eder. Kademeli yükleme shadowban riskini sıfırlar.',
                bestDays: [1, 2, 3, 4, 5, 6, 7]
            ),

            'tiktok' => new PlatformAlgorithmProfile(
                platformCode: 'tiktok',
                platformName: 'TikTok FYP',
                icon: 'heroicon-o-musical-note',
                peakSlotsWeekday: ['09:30', '12:45', '16:15', '19:15', '22:00'],
                peakSlotsWeekend: ['10:30', '13:15', '16:30', '19:45', '22:30'],
                minCooldownMinutes: 90, // 1.5 saat
                maxRecommendedDailyPosts: 5,
                staggerPriority: 2,
                staggerDelayMinutes: 7,
                jitterMinMinutes: 3,
                jitterMaxMinutes: 9,
                algorithmTip: 'FYP havuzunda 200-300 kişilik batch testi yapar. Günde 5 video ile maksimum izlenme kapasitesi kullanılır.',
                bestDays: [1, 2, 3, 4, 5, 6, 7]
            ),

            'youtube' => new PlatformAlgorithmProfile(
                platformCode: 'youtube',
                platformName: 'YouTube Shorts',
                icon: 'heroicon-o-play-circle',
                peakSlotsWeekday: ['09:30', '12:45', '16:15', '19:15', '22:00'],
                peakSlotsWeekend: ['10:30', '13:15', '16:30', '19:45', '22:30'],
                minCooldownMinutes: 90, // 1.5 saat
                maxRecommendedDailyPosts: 5,
                staggerPriority: 3,
                staggerDelayMinutes: 14,
                jitterMinMinutes: 4,
                jitterMaxMinutes: 10,
                algorithmTip: 'Shorts algoritması videoyu yüklendikten 2-4 saat sonra Shorts rafına taşır. Günde 5 video hakkı güvenli aralıklarla tüketilir.',
                bestDays: [1, 2, 3, 4, 5, 6, 7]
            ),

            'facebook' => new PlatformAlgorithmProfile(
                platformCode: 'facebook',
                platformName: 'Facebook Reels & Akış',
                icon: 'heroicon-o-globe-alt',
                peakSlotsWeekday: ['09:30', '12:45', '16:15', '19:15', '22:00'],
                peakSlotsWeekend: ['10:30', '13:15', '16:30', '19:45', '22:30'],
                minCooldownMinutes: 90, // 1.5 saat
                maxRecommendedDailyPosts: 5,
                staggerPriority: 4,
                staggerDelayMinutes: 20,
                jitterMinMinutes: 3,
                jitterMaxMinutes: 8,
                algorithmTip: 'Facebook Reels ve sayfa akışı için 5 zirve dalgası en yüksek organik erişimi sağlar.',
                bestDays: [1, 2, 3, 4, 5, 6, 7]
            ),

            'x' => new PlatformAlgorithmProfile(
                platformCode: 'x',
                platformName: 'X (Twitter)',
                icon: 'heroicon-o-chat-bubble-bottom-center-text',
                peakSlotsWeekday: ['09:30', '12:45', '16:15', '19:15', '22:00'],
                peakSlotsWeekend: ['10:30', '13:15', '16:30', '19:45', '22:30'],
                minCooldownMinutes: 60, // 1 saat
                maxRecommendedDailyPosts: 5,
                staggerPriority: 5,
                staggerDelayMinutes: 26,
                jitterMinMinutes: 2,
                jitterMaxMinutes: 7,
                algorithmTip: 'X anlık tüketim mecrasıdır. 5 zirve dalgasında erişim ve etkileşim katlanır.',
                bestDays: [1, 2, 3, 4, 5, 6, 7]
            ),

            'pinterest' => new PlatformAlgorithmProfile(
                platformCode: 'pinterest',
                platformName: 'Pinterest Pins',
                icon: 'heroicon-o-bookmark',
                peakSlotsWeekday: ['12:45', '16:15', '19:15', '22:00'],
                peakSlotsWeekend: ['13:00', '16:30', '19:30', '22:15'],
                minCooldownMinutes: 90, // 1.5 saat
                maxRecommendedDailyPosts: 4,
                staggerPriority: 6,
                staggerDelayMinutes: 32,
                jitterMinMinutes: 3,
                jitterMaxMinutes: 9,
                algorithmTip: 'Görsel arama motorudur. Öğle ve akşam zirvelerinde pin kaydetme ve kart tıklamaları maksimuma çıkar.',
                bestDays: [1, 2, 3, 4, 5, 6, 7]
            ),

            'linkedin' => new PlatformAlgorithmProfile(
                platformCode: 'linkedin',
                platformName: 'LinkedIn Post',
                icon: 'heroicon-o-briefcase',
                peakSlotsWeekday: ['09:00', '12:30', '16:45', '19:30'],
                peakSlotsWeekend: ['10:30', '13:30', '17:00', '20:30'],
                minCooldownMinutes: 90, // 1.5 saat
                maxRecommendedDailyPosts: 4,
                staggerPriority: 7,
                staggerDelayMinutes: 38,
                jitterMinMinutes: 3,
                jitterMaxMinutes: 8,
                algorithmTip: 'Günde 4 paylaşım ile iş dünyası, e-ticaret ve sektörel kitleye hem hafta içi hem hafta sonu kesintisiz erişim sağlanır.',
                bestDays: [1, 2, 3, 4, 5, 6, 7]
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
     * @param string $strategy 'max_capacity' (5 dalga - tüm haklar), 'balanced' (3 dalga), 'conservative' (2 dalga)
     * @return array<string> ['09:30', '12:45', '16:15', '19:15', '22:00'] gibi
     */
    public function getAlgorithmicRunTimes(string $strategy = 'max_capacity', ?Carbon $date = null): array
    {
        $date = $date ?: now();
        $isWeekend = $date->isWeekend();

        // 1. Maksimum Güç & Tam Kapasite: Her platformun maksimum hakkını sonuna kadar kullanan 5 dalga
        if ($strategy === 'max_capacity' || $strategy === 'aggressive') {
            return $isWeekend
                ? ['10:30', '13:15', '16:30', '19:45', '22:30']
                : ['09:30', '12:45', '16:15', '19:15', '22:00'];
        }

        // 2. Hafif / Güvenli Mod: 2 dalga
        if ($strategy === 'conservative') {
            return $isWeekend
                ? ['12:00', '19:30']
                : ['12:15', '19:15'];
        }

        // 3. Dengeli Mod: 3 dalga
        return $isWeekend
            ? ['11:30', '16:30', '20:45']
            : ['12:15', '17:45', '21:10'];
    }

    /**
     * Bir sosyal hesabın bu dalga zamanında paylaşım yapmaya uygun olup olmadığını denetler.
     * Platformun günlük maksimum hak sınırını, haftanın gününü ve minimum soğuma süresini korur.
     */
    public function isAccountEligibleForWave(
        SocialAccount $account,
        Carbon $targetTime,
        string $strategy = 'max_capacity'
    ): bool {
        $code = $account->platform?->code?->value ?? (string) $account->platform?->code;
        $profile = $this->getProfile($code);

        // 1. Gün kontrolü (Örn: LinkedIn sadece hafta içi iş günleri yayın yapar)
        $dayOfWeekIso = $targetTime->dayOfWeekIso; // 1 = Pazartesi, 7 = Pazar
        if (! in_array($dayOfWeekIso, $profile->bestDays, true)) {
            return false;
        }

        // 2. Bugün bu hesap için kaç gönderi planlandı / yayınlandı?
        $todayPostsCount = ScheduledPostTarget::where('social_account_id', $account->id)
            ->whereDate('scheduled_at', $targetTime->toDateString())
            ->whereIn('status', [
                \App\Enums\PublishStatus::SCHEDULED,
                \App\Enums\PublishStatus::QUEUED,
                \App\Enums\PublishStatus::PROCESSING,
                \App\Enums\PublishStatus::PUBLISHED
            ])
            ->count();

        // Stratejiye göre izin verilen günlük tavan
        $maxDaily = $profile->maxRecommendedDailyPosts;
        if ($strategy === 'conservative') {
            $maxDaily = min($maxDaily, 2);
        } elseif ($strategy === 'balanced') {
            $maxDaily = min($maxDaily, 3);
        }

        // Maksimum günlük limit dolduysa bu dalgada atla (başka platformlar devam eder)
        if ($todayPostsCount >= $maxDaily) {
            return false;
        }

        // 3. Minimum soğuma kontrolü (son gönderiden bu yana yeterli süre geçti mi?)
        $lastTarget = ScheduledPostTarget::where('social_account_id', $account->id)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<', $targetTime)
            ->whereIn('status', [
                \App\Enums\PublishStatus::SCHEDULED,
                \App\Enums\PublishStatus::QUEUED,
                \App\Enums\PublishStatus::PROCESSING,
                \App\Enums\PublishStatus::PUBLISHED
            ])
            ->orderBy('scheduled_at', 'desc')
            ->first();

        if ($lastTarget && $lastTarget->scheduled_at) {
            $diffMinutes = $lastTarget->scheduled_at->diffInMinutes($targetTime, false);
            // Soğuma süresi henüz dolmadıysa bu dalgada dinlendir (15 dk tolerans)
            if ($diffMinutes < ($profile->minCooldownMinutes - 15)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verilen hesap listesinden bu dalga için kotaları ve soğuma süresi uygun olanları filtreler
     */
    public function filterEligibleAccounts(iterable $accounts, Carbon $targetTime, string $strategy = 'max_capacity'): Collection
    {
        $collection = $accounts instanceof Collection ? $accounts : collect($accounts);

        return $collection->filter(function (SocialAccount $account) use ($targetTime, $strategy) {
            return $this->isAccountEligibleForWave($account, $targetTime, $strategy);
        })->values();
    }

    /**
     * Başlangıç tarihinden itibaren geleceğe doğru N adet algoritmik dalga zamanı üretir.
     *
     * @return array<Carbon>
     */
    public function generateUpcomingWaveSlots(Carbon $startDate, int $count, string $strategy = 'max_capacity'): array
    {
        $slots = [];
        $currentDate = $startDate->copy()->startOfDay();
        $now = $startDate->copy();

        while (count($slots) < $count) {
            $dailyTimes = $this->getAlgorithmicRunTimes($strategy, $currentDate);

            foreach ($dailyTimes as $timeStr) {
                $slotTime = $currentDate->copy()->setTimeFromTimeString($timeStr);

                // Eğer başlangıç anından önceyse atla
                if ($slotTime->lessThan($now)) {
                    continue;
                }

                $slots[] = $slotTime;

                if (count($slots) >= $count) {
                    break;
                }
            }

            $currentDate->addDay();
        }

        return $slots;
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
