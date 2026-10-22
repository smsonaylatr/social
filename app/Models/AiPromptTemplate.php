<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPromptTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'platform_code',
        'content_type',
        'system_prompt',
        'user_prompt_template',
        'ai_model',
        'temperature',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'temperature' => 'decimal:2',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
