<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'name',
        'slug',
        'color',
        'description',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class, 'category_id');
    }
}
