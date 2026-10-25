<?php

namespace App\Services\Scheduling;

class PlatformAlgorithmProfile
{
    public function __construct(
        public string $platformCode,
        public string $platformName,
        public string $icon,
        public array $peakSlotsWeekday,
        public array $peakSlotsWeekend,
        public int $minCooldownMinutes,
        public int $maxRecommendedDailyPosts,
        public int $staggerPriority,
        public int $staggerDelayMinutes,
        public int $jitterMinMinutes,
        public int $jitterMaxMinutes,
        public string $algorithmTip,
        public array $bestDays = [1, 2, 3, 4, 5, 6, 7]
    ) {}

    /**
     * Get peak slots for today (weekday vs weekend)
     */
    public function getPeakSlotsForDay(bool $isWeekend): array
    {
        return $isWeekend ? $this->peakSlotsWeekend : $this->peakSlotsWeekday;
    }
}
