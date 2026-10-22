<?php

namespace App\Models;

use App\Enums\SocialPlatformCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialPlatform extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'icon',
        'description',
        'is_active',
        'supported_features',
        'daily_post_limit',
        'app_credentials_schema',
    ];

    protected $casts = [
        'code' => SocialPlatformCode::class,
        'is_active' => 'boolean',
        'supported_features' => 'array',
        'app_credentials_schema' => 'array',
    ];

    public function accounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class, 'platform_id');
    }
}
