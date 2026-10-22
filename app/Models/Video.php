<?php

namespace App\Models;

use App\Enums\VideoStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'category_id',
        'title',
        'description',
        'original_filename',
        'storage_path',
        'disk',
        'file_hash',
        'file_size',
        'duration_seconds',
        'width',
        'height',
        'aspect_ratio',
        'fps',
        'mime_type',
        'thumbnail_path',
        'status',
        'usage_count',
        'last_used_at',
        'is_reusable',
        'restricted_platforms',
        'product_url',
        'custom_caption',
        'custom_hashtags',
        'technical_metadata',
    ];

    protected $casts = [
        'status' => VideoStatus::class,
        'is_reusable' => 'boolean',
        'restricted_platforms' => 'array',
        'custom_hashtags' => 'array',
        'technical_metadata' => 'array',
        'last_used_at' => 'datetime',
        'fps' => 'decimal:2',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VideoCategory::class, 'category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(VideoVariant::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(VideoTag::class);
    }

    public function playlistItems(): HasMany
    {
        return $this->hasMany(PlaylistItem::class);
    }

    public function scheduledPosts(): HasMany
    {
        return $this->hasMany(ScheduledPost::class);
    }

    public function aiGenerations(): HasMany
    {
        return $this->hasMany(AiContentGeneration::class);
    }

    public function getUrlAttribute(): ?string
    {
        if (! $this->storage_path) {
            return null;
        }

        return Storage::disk($this->disk ?? 'public')->url($this->storage_path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }

        return Storage::disk($this->disk ?? 'public')->url($this->thumbnail_path);
    }

    public function getFormattedDurationAttribute(): string
    {
        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
