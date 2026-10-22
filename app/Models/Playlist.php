<?php

namespace App\Models;

use App\Enums\PlaylistLoopMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Playlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'name',
        'slug',
        'description',
        'loop_mode',
        'is_circular',
        'cooldown_days',
        'pause_when_exhausted',
        'prioritize_new_videos',
        'status',
        'platform_rules',
    ];

    protected $casts = [
        'loop_mode' => PlaylistLoopMode::class,
        'is_circular' => 'boolean',
        'pause_when_exhausted' => 'boolean',
        'prioritize_new_videos' => 'boolean',
        'platform_rules' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlaylistItem::class)->orderBy('sort_order', 'asc');
    }

    public function cursor(): HasOne
    {
        return $this->hasOne(PlaylistCursor::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }
}
