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
        'is_active' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
    ];

    /**
     * Gün içinde bu kuralın tetikleneceği tüm saatleri 'HH:mm' formatında sıralı dizi olarak döndürür
     */
    public function getDailyRunTimes(): array
    {
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

            return ! empty($times) ? $times : [substr($this->time_of_day ?: '12:00', 0, 5)];
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

            return ! empty($times) ? $times : [substr($this->time_of_day ?: '12:00', 0, 5)];
        }

        // 3. Belirli özel saatler modu
        if ($this->schedule_type === 'custom_times' && ! empty($this->posting_times)) {
            $times = array_values(array_unique(array_filter(array_map('trim', (array) $this->posting_times))));
            sort($times);
            return ! empty($times) ? $times : [substr($this->time_of_day ?: '12:00', 0, 5)];
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

            return ! empty($times) ? $times : [substr($this->time_of_day ?: '12:00', 0, 5)];
        }

        // 5. Tek saat modu (varsayılan)
        return [substr($this->time_of_day ?: '12:00', 0, 5)];
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
