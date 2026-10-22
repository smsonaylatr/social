<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiContentGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'brand_id',
        'platform_code',
        'title',
        'hook_sentence',
        'caption',
        'hashtags',
        'seo_keywords',
        'call_to_action',
        'suggested_product_url',
        'ai_model',
        'prompt_tokens',
        'completion_tokens',
        'cost_usd',
        'status',
        'is_active',
    ];

    protected $casts = [
        'hashtags' => 'array',
        'seo_keywords' => 'array',
        'cost_usd' => 'decimal:6',
        'is_active' => 'boolean',
    ];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
