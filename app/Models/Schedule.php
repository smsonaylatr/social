<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'playlist_id',
        'campaign_id',
        'name',
        'schedule_type',
        'growth_strategy',
        'anti_spam_shield',
        'enable_smart_jitter',
        'enable_staggered_dispatch',
        'time_of_day',
        'posting_times',
        'interval_hours',
        'interval_minutes',
        'start_time',
        'end_time',
        'days_of_week',
        'cron_expression',
        'target_social_account_ids',
        'max_posts_per_day',
        'is_active',
        'last_run_at',
        'next_run_at',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'posting_times' => 'array',
        'target_social_account_ids' => 'array',
        'interval_hours' => 'integer',
        'interval_minutes' => 'integer',
        'anti_spam_shield' => 'boolean',
        'enable_smart_jitter' => 'boolean',
        'enable_staggered_dispatch' => 'boolean',
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    protected ?array $memoizedRunTimes = null;

    /**
     * Gün içinde bu kuralın tetikleneceği tüm saatleri 'HH:mm' formatında sıralı dizi olarak döndürür
     */
    public function getDailyRunTimes(): array
    {
        if ($this->memoizedRunTimes !== null) {
            return $this->memoizedRunTimes;
        }

        // 0. Algoritmik Keşfet & Organik Büyüme Modu (Platform Zirve Saatleri)
        if ($this->schedule_type === 'algorithmic_growth') {
            if (! empty($this->posting_times) && is_array($this->posting_times)) {
                return $this->memoizedRunTimes = $this->posting_times;
            }

            if (! empty($this->custom_run_times)) {
                return $this->memoizedRunTimes = $this->custom_run_times;
            }

            /** @var \App\Services\Scheduling\AlgorithmicGrowthSchedulerService $algoService */
            $algoService = app(\App\Services\Scheduling\AlgorithmicGrowthSchedulerService::class);

            // 1. Hedeflenen hesap tek bir platforma aitse o platformun algoritma zirvelerini döndür
            $targetAccountIds = $this->target_social_account_ids ?: [];
            $platformCode = null;
            if (count($targetAccountIds) === 1) {
                $account = \App\Models\SocialAccount::with('platform')->find($targetAccountIds[0]);
                if ($account) {
                    $platformCode = strtolower($account->platform?->code?->value ?? (string) $account->platform?->code ?? (string) $account->platform_id);
                }
            }

            // 2. Kural adından tespit (örn: "Instagram Reels", "YouTube Shorts", "Facebook", "X (Twitter)", "LinkedIn")
            if (empty($platformCode) && ! empty($this->name)) {
                $nameLower = mb_strtolower($this->name);
                $platformCode = match (true) {
                    str_contains($nameLower, 'instagram') => 'instagram',
                    str_contains($nameLower, 'tiktok') => 'tiktok',
                    str_contains($nameLower, 'youtube') => 'youtube',
                    str_contains($nameLower, 'facebook') => 'facebook',
                    str_contains($nameLower, 'linkedin') => 'linkedin',
                    str_contains($nameLower, 'pinterest') => 'pinterest',
                    str_contains($nameLower, 'twitter') || preg_match('/\bx\b/u', $nameLower) => 'x',
                    default => null,
                };
            }

            if (! empty($platformCode)) {
                $profile = $algoService->getProfile($platformCode);
                $slots = now()->isWeekend() ? $profile->peakSlotsWeekend : $profile->peakSlotsWeekday;
                if (! empty($slots)) {
                    return $this->memoizedRunTimes = $slots;
                }
            }

            return $this->memoizedRunTimes = $algoService->getAlgorithmicRunTimes(
                $this->growth_strategy ?: 'max_capacity'
            );
        }

        // 1. Her saat başı modu
        if ($this->schedule_type === 'hourly') {
            $times = [];
            $startStr = $this->start_time ? substr($this->start_time, 0, 5) : '00:00';
            $endStr = $this->end_time ? substr($this->end_time, 0, 5) : '23:59';

            $start = \Carbon\Carbon::createFromFormat('H:i', $startStr);
            $end = \Carbon\Carbon::createFromFormat('H:i', $endStr);

            if ($end->lessThan($start)) {
                $end->addDay();
            }

            $current = $start->copy();
            while ($current->lessThanOrEqualTo($end)) {
                $times[] = $current->format('H:i');
                $current->addHour();
            }

            return $this->memoizedRunTimes = (! empty($times) ? $times : [substr($this->time_of_day ?: '12:00', 0, 5)]);
        }

        // 2. Belirli dakikada bir paylaşım modu (örn: her 15, 30, 45 dakikada bir)
        if ($this->schedule_type === 'minute_interval' && $this->interval_minutes > 0) {
            $times = [];
            $startStr = $this->start_time ? substr($this->start_time, 0, 5) : '00:00';
            $endStr = $this->end_time ? substr($this->end_time, 0, 5) : '23:59';

            $start = \Carbon\Carbon::createFromFormat('H:i', $startStr);
            $end = \Carbon\Carbon::createFromFormat('H:i', $endStr);

            if ($end->lessThan($start)) {
                $end->addDay();
            }

            $step = max(1, (int) $this->interval_minutes);
            $current = $start->copy();
            while ($current->lessThanOrEqualTo($end)) {
                $times[] = $current->format('H:i');
                $current->addMinutes($step);
            }

            return $this->memoizedRunTimes = (! empty($times) ? $times : [substr($this->time_of_day ?: '12:00', 0, 5)]);
        }

        // 3. Belirli özel saatler modu
        if ($this->schedule_type === 'custom_times' && ! empty($this->posting_times)) {
            $times = array_values(array_unique(array_filter(array_map('trim', (array) $this->posting_times))));
            sort($times);
            return $this->memoizedRunTimes = (! empty($times) ? $times : [substr($this->time_of_day ?: '12:00', 0, 5)]);
        }

        // 4. Belirli saat aralıkları modu (örn: her 2 saatte bir)
        if ($this->schedule_type === 'interval' && $this->interval_hours > 0) {
            $times = [];
            $startStr = $this->start_time ? substr($this->start_time, 0, 5) : '00:00';
            $endStr = $this->end_time ? substr($this->end_time, 0, 5) : '23:59';

            $start = \Carbon\Carbon::createFromFormat('H:i', $startStr);
            $end = \Carbon\Carbon::createFromFormat('H:i', $endStr);

            if ($end->lessThan($start)) {
                $end->addDay();
            }

            $current = $start->copy();
            while ($current->lessThanOrEqualTo($end)) {
                $times[] = $current->format('H:i');
                $current->addHours($this->interval_hours);
            }

            return $this->memoizedRunTimes = (! empty($times) ? $times : [substr($this->time_of_day ?: '12:00', 0, 5)]);
        }

        // 5. Tek saat modu (varsayılan)
        return $this->memoizedRunTimes = [substr($this->time_of_day ?: '12:00', 0, 5)];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function scheduledPosts(): HasMany
    {
        return $this->hasMany(ScheduledPost::class);
    }
}
