<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccountPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'social_account_id',
        'permission_name',
        'status',
        'verified_at',
        'review_notes',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }
}
