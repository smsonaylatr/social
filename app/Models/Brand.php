<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'slug',
        'website',
        'slogan',
        'brand_tone',
        'target_audience',
        'brand_language',
        'seo_keywords',
        'forbidden_words',
        'default_cta',
        'landing_urls',
        'campaign_notes',
        'is_active',
    ];

    protected $casts = [
        'seo_keywords' => 'array',
        'forbidden_words' => 'array',
        'landing_urls' => 'array',
        'is_active' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Video::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}
