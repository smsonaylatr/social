<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class VideoVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'variant_type',
        'storage_path',
        'disk',
        'file_size',
        'width',
        'height',
        'aspect_ratio',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function getUrlAttribute(): ?string
    {
        return Storage::disk($this->disk ?? 'public')->url($this->storage_path);
    }
}
