<?php

namespace App\Models;

use App\Enums\PublishStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduledPostTarget extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheduled_post_id',
        'social_account_id',
        'platform_code',
        'scheduled_at',
        'correlation_id',
        'idempotency_key',
        'status',
        'title',
        'caption',
        'hashtags',
        'product_url',
        'external_post_id',
        'external_post_url',
        'attempt_count',
        'published_at',
        'error_message',
        'error_details',
    ];

    protected $casts = [
        'status' => PublishStatus::class,
        'scheduled_at' => 'datetime',
        'hashtags' => 'array',
        'error_details' => 'array',
        'published_at' => 'datetime',
    ];

    public function scheduledPost(): BelongsTo
    {
        return $this->belongsTo(ScheduledPost::class);
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(PublicationAttempt::class);
    }
}
