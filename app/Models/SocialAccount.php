<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'platform_id',
        'account_id',
        'account_type',
        'name',
        'username',
        'avatar_url',
        'profile_url',
        'status',
        'last_synced_at',
        'last_posted_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'last_posted_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(SocialPlatform::class, 'platform_id');
    }

    public function credential(): HasOne
    {
        return $this->hasOne(SocialAccountCredential::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(SocialAccountPermission::class);
    }

    public function postTargets(): HasMany
    {
        return $this->hasMany(ScheduledPostTarget::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'active' && $this->credential()->exists();
    }
}
