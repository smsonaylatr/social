<?php

namespace App\Models;

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
        'platform_filter',
        'priority',
        'usage_count',
        'last_used_at',
        'is_blacklisted',
        'is_active',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
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
}
