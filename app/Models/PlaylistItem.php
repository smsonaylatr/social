<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaylistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'playlist_id',
        'video_id',
        'sort_order',
        'priority_score',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
