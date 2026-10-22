<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'provider',
        'endpoint',
        'method',
        'status_code',
        'response_time_ms',
        'prompt_tokens',
        'completion_tokens',
        'estimated_cost_usd',
        'is_error',
        'error_message',
    ];

    protected $casts = [
        'response_time_ms' => 'decimal:2',
        'estimated_cost_usd' => 'decimal:6',
        'is_error' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
