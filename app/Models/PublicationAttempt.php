<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'scheduled_post_target_id',
        'attempt_number',
        'status',
        'http_status_code',
        'request_payload_summary',
        'response_summary',
        'error_message',
        'attempted_at',
    ];

    protected $casts = [
        'attempted_at' => 'datetime',
    ];

    public function target(): BelongsTo
    {
        return $this->belongsTo(ScheduledPostTarget::class, 'scheduled_post_target_id');
    }
}
