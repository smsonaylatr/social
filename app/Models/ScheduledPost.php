<?php

namespace App\Models;

use App\Enums\PublishStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'video_id',
        'post_type',
        'playlist_id',
        'schedule_id',
        'campaign_id',
        'scheduled_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'status' => PublishStatus::class,
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Gönderinin paylaşılan / planlanan açıklama metnini döner
     */
    public function getCaptionAttribute(): ?string
    {
        $target = $this->targets->first(fn ($t) => ! empty(trim((string) $t->caption)));
        return $target?->caption
            ?: ($this->video?->custom_caption ?: ($this->video?->description ?: null));
    }

    /**
     * Hedeflerin son durumlarına göre ana gönderi statüsünü otomatik günceller
     */
    public function updateStatusBasedOnTargets(): void
    {
        $targets = $this->targets()->get();
        if ($targets->isEmpty()) {
            return;
        }

        $total = $targets->count();
        $published = $targets->where('status', PublishStatus::PUBLISHED)->count();
        $failed = $targets->where('status', PublishStatus::FAILED)->count();
        $activeInQueue = $targets->whereIn('status', [
            PublishStatus::QUEUED,
            PublishStatus::SCHEDULED,
            PublishStatus::UPLOADING,
            PublishStatus::PROCESSING,
            PublishStatus::RETRYING,
        ])->count();

        if ($published === $total) {
            $this->update(['status' => PublishStatus::PUBLISHED]);
        } elseif ($activeInQueue > 0) {
            // Eğer kuyrukta bekleyen hedef varsa ve bazıları yayınlandıysa bile ana durum KUYRUKTA olmalı
            $this->update(['status' => PublishStatus::QUEUED]);
        } elseif ($published > 0 && ($published + $failed === $total)) {
            $this->update(['status' => PublishStatus::PARTIALLY_PUBLISHED]);
        } elseif ($failed === $total) {
            $this->update(['status' => PublishStatus::FAILED]);
        }
    }

    public function targets(): HasMany
    {
        return $this->hasMany(ScheduledPostTarget::class);
    }
}
