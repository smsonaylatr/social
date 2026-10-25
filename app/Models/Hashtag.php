<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hashtag extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'group_id',
        'tag',
        'category',
        'is_trending',
        'platform_filter',
        'priority',
        'usage_count',
        'last_used_at',
        'expires_at',
        'is_blacklisted',
        'is_active',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_trending' => 'boolean',
        'is_blacklisted' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(HashtagGroup::class, 'group_id');
    }

    /**
     * Scope: Aktif ve süresi dolmamış etiketler
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scope: Kara listede olmayanlar
     */
    public function scopeNotBlacklisted(Builder $query): Builder
    {
        return $query->where('is_blacklisted', false);
    }

    /**
     * Scope: Revaçtaki (trend) etiketler
     */
    public function scopeTrending(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('is_trending', true)
              ->orWhere('category', 'trending');
        });
    }

    /**
     * Scope: Belirli bir platform veya global
     */
    public function scopeForPlatform(Builder $query, string $platformCode): Builder
    {
        return $query->where(function (Builder $q) use ($platformCode) {
            $q->whereNull('platform_filter')
              ->orWhere('platform_filter', strtolower($platformCode));
        });
    }
}
