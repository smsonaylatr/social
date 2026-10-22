<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KeywordLibrary extends Model
{
    use HasFactory;

    protected $table = 'keyword_library';

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'keyword',
        'language',
        'category',
        'search_intent',
        'priority',
        'target_url',
        'platform',
        'usage_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
