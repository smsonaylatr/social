<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class LiveStream extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'brand_id',
        'target_platform',
        'title',
        'description',
        'source_type',
        'playlist_id',
        'custom_video_ids',
        'play_order',
        'aspect_ratio_filter',
        'resolution_mode',
        'bitrate_kbps',
        'fps',
        'is_loop',
        'auto_restart',
        'mute_original_audio',
        'audio_mode',
        'bg_music_source',
        'bg_music_preset',
        'bg_music_path',
        'bg_music_volume',
        'original_audio_volume',
        'youtube_enabled',
        'youtube_account_id',
        'youtube_rtmp_url',
        'youtube_stream_key',
        'youtube_broadcast_id',
        'youtube_watch_url',
        'instagram_enabled',
        'instagram_account_id',
        'instagram_rtmp_url',
        'instagram_stream_key',
        'tiktok_enabled',
        'tiktok_account_id',
        'tiktok_rtmp_url',
        'tiktok_stream_key',
        'custom_rtmp_enabled',
        'custom_rtmp_name',
        'custom_rtmp_url',
        'custom_rtmp_key',
        'status',
        'process_pid',
        'total_videos_count',
        'started_at',
        'stopped_at',
        'last_heartbeat_at',
        'restart_count',
        'last_error',
    ];

    protected $casts = [
        'custom_video_ids' => 'array',
        'bitrate_kbps' => 'integer',
        'fps' => 'integer',
        'is_loop' => 'boolean',
        'auto_restart' => 'boolean',
        'mute_original_audio' => 'boolean',
        'bg_music_volume' => 'integer',
        'original_audio_volume' => 'integer',
        'youtube_enabled' => 'boolean',
        'instagram_enabled' => 'boolean',
        'tiktok_enabled' => 'boolean',
        'custom_rtmp_enabled' => 'boolean',
        'process_pid' => 'integer',
        'total_videos_count' => 'integer',
        'restart_count' => 'integer',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (LiveStream $stream) {
            if (! $stream->workspace_id) {
                $stream->workspace_id = Workspace::first()?->id;
            }
            if (! $stream->brand_id) {
                $stream->brand_id = Brand::first()?->id;
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function youtubeAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'youtube_account_id');
    }

    public function instagramAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'instagram_account_id');
    }

    public function tiktokAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'tiktok_account_id');
    }

    /**
     * Bu canlı yayının band kaydı döngüsüne girecek videoları sıralı/filtrelenmiş olarak getirir.
     */
    public function getEligibleVideos(): Collection
    {
        if ($this->source_type === 'playlist' && $this->playlist_id) {
            $playlist = Playlist::with(['items.video'])->find($this->playlist_id);
            if ($playlist) {
                $videos = $playlist->items
                    ->where('is_active', true)
                    ->map(fn ($item) => $item->video)
                    ->filter();
            } else {
                $videos = collect();
            }
        } elseif ($this->source_type === 'custom' && ! empty($this->custom_video_ids)) {
            $ids = array_map('intval', (array) $this->custom_video_ids);
            $videosById = Video::whereIn('id', $ids)->get()->keyBy('id');
            $videos = collect($ids)->map(fn ($id) => $videosById->get($id))->filter();
        } else {
            // Varsayılan: Tüm Video Havuzu (pool)
            $query = Video::whereIn('status', ['ready', 'active', 'published']);

            if ($this->aspect_ratio_filter && $this->aspect_ratio_filter !== 'all') {
                $query->where('aspect_ratio', $this->aspect_ratio_filter);
            }

            if ($this->play_order === 'reverse') {
                $query->orderBy('id', 'desc');
            } else {
                $query->orderBy('id', 'asc');
            }

            $videos = $query->get();
        }

        // Aspect ratio filter for playlist/custom as well if set
        if ($this->aspect_ratio_filter && $this->aspect_ratio_filter !== 'all') {
            $videos = $videos->filter(fn (Video $v) => $v->aspect_ratio === $this->aspect_ratio_filter);
        }

        // Sadece diskte fiziksel olarak mevcut olan geçerli MP4/video dosyalarını tut
        $validVideos = $videos->filter(function (Video $video) {
            if (! $video->storage_path) {
                return false;
            }
            $disk = $video->disk ?: 'public';
            return Storage::disk($disk)->exists($video->storage_path);
        })->values();

        if ($this->play_order === 'shuffle') {
            return $validVideos->shuffle()->values();
        }

        return $validVideos;
    }

    /**
     * Bir tam döngünün toplam süresini (saniye) hesaplar.
     */
    public function getTotalLoopDurationSeconds(): int
    {
        return (int) $this->getEligibleVideos()->sum('duration_seconds');
    }

    public function getFormattedLoopDurationAttribute(): string
    {
        $totalSeconds = $this->getTotalLoopDurationSeconds();
        if ($totalSeconds <= 0) {
            return '00:00';
        }

        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return sprintf('%d sa %02d dk %02d sn', $hours, $minutes, $seconds);
        }

        return sprintf('%02d dk %02d sn', $minutes, $seconds);
    }

    public function getUptimeFormattedAttribute(): string
    {
        if ($this->status !== 'running' || ! $this->started_at) {
            return '—';
        }

        $diffInSeconds = max(0, (int) $this->started_at->diffInSeconds(now()));
        $days = floor($diffInSeconds / 86400);
        $hours = floor(($diffInSeconds % 86400) / 3600);
        $minutes = floor(($diffInSeconds % 3600) / 60);
        $seconds = $diffInSeconds % 60;

        if ($days > 0) {
            return sprintf('%dg %02ds %02ddk', $days, $hours, $minutes);
        }
        if ($hours > 0) {
            return sprintf('%02ds %02ddk %02dsn', $hours, $minutes, $seconds);
        }

        return sprintf('%02ddk %02dsn', $minutes, $seconds);
    }

    /**
     * Aktif hedef RTMP uçlarını dizi olarak döndürür.
     */
    public function getActiveRtmpEndpoints(): array
    {
        $endpoints = [];

        if ($this->youtube_enabled && ! empty(trim((string) $this->youtube_stream_key))) {
            $baseUrl = rtrim(trim((string) ($this->youtube_rtmp_url ?: 'rtmp://a.rtmp.youtube.com/live2')), '/');
            $key = ltrim(trim((string) $this->youtube_stream_key), '/');
            $endpoints['youtube'] = "{$baseUrl}/{$key}";
        }

        if ($this->instagram_enabled && ! empty(trim((string) $this->instagram_stream_key))) {
            $rawUrl = trim((string) ($this->instagram_rtmp_url ?: 'rtmps://live-upload.instagram.com:443/rtmp'));
            $baseUrl = rtrim($rawUrl, '/');
            $key = ltrim(trim((string) $this->instagram_stream_key), '/');
            $endpoints['instagram'] = "{$baseUrl}/{$key}";
        }

        if ($this->tiktok_enabled && ! empty(trim((string) $this->tiktok_stream_key))) {
            $baseUrl = rtrim(trim((string) ($this->tiktok_rtmp_url ?: 'rtmp://live-push.tiktok.com/live/')), '/');
            $key = ltrim(trim((string) $this->tiktok_stream_key), '/');
            $endpoints['tiktok'] = "{$baseUrl}/{$key}";
        }

        if ($this->custom_rtmp_enabled && ! empty(trim((string) $this->custom_rtmp_url))) {
            $baseUrl = rtrim(trim((string) $this->custom_rtmp_url), '/');
            $key = ltrim(trim((string) $this->custom_rtmp_key), '/');
            $endpoints['custom'] = $key !== '' ? "{$baseUrl}/{$key}" : $baseUrl;
        }

        return $endpoints;
    }
}
