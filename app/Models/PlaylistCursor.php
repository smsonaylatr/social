<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaylistCursor extends Model
{
    use HasFactory;

    protected $fillable = [
        'playlist_id',
        'current_index',
        'current_video_id',
        'cycle_count',
        'last_dispatched_at',
    ];

    protected $casts = [
        'last_dispatched_at' => 'datetime',
    ];

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function currentVideo(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'current_video_id');
    }
}
