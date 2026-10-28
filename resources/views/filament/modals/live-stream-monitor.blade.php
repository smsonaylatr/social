@php
    /** @var \App\Models\LiveStream $stream */
    $service = app(\App\Services\LiveStream\LiveStreamService::class);
    $isRunning = $stream->status === 'running' && $stream->process_pid && $service->isProcessRunning($stream->process_pid);
    $videos = $stream->getEligibleVideos();
    $endpoints = $stream->getActiveRtmpEndpoints();
    $logs = $service->getRecentLogs($stream, 45);
    $ffmpegAvailable = $service->isFfmpegAvailable();
    $ffmpegVersion = $service->getFfmpegVersion();
    try {
        $ffmpegCommand = $service->buildFfmpegCommand($stream);
    } catch (\Throwable $e) {
        $ffmpegCommand = 'Komut oluşturulamadı: ' . $e->getMessage();
    }
@endphp

<div class="space-y-4 text-sm">
    {{-- Üst Durum Kartları --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60">
            <div class="text-xs text-gray-400 mb-1">Yayın Durumu</div>
            <div class="font-bold flex items-center gap-2">
                @if($isRunning)
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-emerald-400">CANLI YAYINDA (PID: {{ $stream->process_pid }})</span>
                @elseif($stream->status === 'error')
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                    <span class="text-rose-400">HATA / DURDU</span>
                @else
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-gray-500"></span>
                    <span class="text-gray-300">DURDURULDU</span>
                @endif
            </div>
        </div>

        <div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60">
            <div class="text-xs text-gray-400 mb-1">Kesintisiz Süre (Uptime)</div>
            <div class="font-bold text-white">{{ $stream->uptime_formatted }}</div>
            @if($stream->restart_count > 0)
                <div class="text-[11px] text-amber-400 mt-0.5">Watchdog kurtarma: {{ $stream->restart_count }}x</div>
            @endif
        </div>

        <div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60">
            <div class="text-xs text-gray-400 mb-1">Havuz & Döngü Süresi</div>
            <div class="font-bold text-sky-400">{{ $videos->count() }} Video ({{ $stream->formatted_loop_duration }})</div>
            <div class="text-[11px] text-gray-400 mt-0.5">{{ $stream->is_loop ? '♾️ Sonsuz Band Döngüsü' : '1 Kez Oynat' }}</div>
        </div>

        <div class="p-3 rounded-lg border border-gray-700 bg-gray-900/60">
            <div class="text-xs text-gray-400 mb-1">FFmpeg Motoru</div>
            <div class="font-bold {{ $ffmpegAvailable ? 'text-emerald-400' : 'text-rose-400' }}">
                {{ $ffmpegAvailable ? '✓ Hazır & Aktif' : '✗ Bulunamadı' }}
            </div>
            <div class="text-[10px] text-gray-400 truncate mt-0.5" title="{{ $ffmpegVersion }}">{{ $ffmpegVersion ?: 'FFmpeg kurulu değil' }}</div>
        </div>
    </div>

    @if($stream->last_error)
        <div class="p-3 rounded-lg border border-rose-500/40 bg-rose-950/40 text-rose-200 text-xs">
            <strong class="block text-rose-400 mb-1">⚠️ Son Hata Kaydı:</strong>
            <pre class="whitespace-pre-wrap font-mono text-[11px]">{{ $stream->last_error }}</pre>
        </div>
    @endif

    {{-- Aktif Hedef Platformlar --}}
    <div class="p-3 rounded-lg border border-gray-700 bg-gray-900/40">
        <div class="text-xs font-semibold text-gray-300 mb-2">📡 Aktif RTMP / RTMPS Yayın Hedefleri</div>
        <div class="flex flex-wrap gap-2">
            @if($stream->youtube_enabled)
                <span class="px-2.5 py-1 rounded-md bg-red-600/20 border border-red-500/40 text-red-300 text-xs font-medium">
                    ▶️ YouTube Live ({{ !empty($stream->youtube_stream_key) ? 'Anahtar Tanımlı' : 'Anahtar Eksik!' }})
                </span>
            @endif
            @if($stream->instagram_enabled)
                <span class="px-2.5 py-1 rounded-md bg-pink-600/20 border border-pink-500/40 text-pink-300 text-xs font-medium">
                    📸 Instagram Live ({{ !empty($stream->instagram_stream_key) ? 'Anahtar Tanımlı' : 'Anahtar Eksik!' }})
                </span>
            @endif
            @if($stream->custom_rtmp_enabled)
                <span class="px-2.5 py-1 rounded-md bg-indigo-600/20 border border-indigo-500/40 text-indigo-300 text-xs font-medium">
                    🌐 Özel RTMP ({{ $stream->custom_rtmp_name ?: 'Ek Platform' }})
                </span>
            @endif
            @if($stream->youtube_watch_url)
                <a href="{{ $stream->youtube_watch_url }}" target="_blank" class="px-2.5 py-1 rounded-md bg-sky-600/20 border border-sky-500/40 text-sky-300 text-xs font-medium hover:underline">
                    🔗 YouTube Yayınını Aç ↗
                </a>
            @endif
        </div>
    </div>

    {{-- Band Kaydındaki Videoların Sırası --}}
    <div class="p-3 rounded-lg border border-gray-700 bg-gray-900/40">
        <div class="text-xs font-semibold text-gray-300 mb-2">
            🎬 Band Kaydı Döngüsündeki Videolar (İlk 15 Video Önizlemesi — Toplam {{ $videos->count() }} Video)
        </div>
        <div class="flex flex-wrap gap-1.5 max-h-28 overflow-y-auto">
            @forelse($videos->take(20) as $idx => $v)
                <span class="px-2 py-0.5 rounded bg-gray-800 border border-gray-700 text-[11px] text-gray-200">
                    #{{ $idx + 1 }} {{ $v->title }} ({{ $v->formatted_duration }})
                </span>
            @empty
                <span class="text-xs text-amber-400">Havuzda uygun video bulunamadı.</span>
            @endforelse
            @if($videos->count() > 20)
                <span class="px-2 py-0.5 rounded bg-indigo-950 border border-indigo-700 text-[11px] text-indigo-300">
                    +{{ $videos->count() - 20 }} video daha...
                </span>
            @endif
        </div>
    </div>

    {{-- Canlı FFmpeg Konsol Çıktısı --}}
    <div>
        <div class="flex items-center justify-between mb-1">
            <span class="text-xs font-semibold text-gray-300">🖥️ Canlı FFmpeg Yayın Konsolu (Son 45 Satır)</span>
            <span class="text-[11px] text-gray-400">{{ $stream->resolution_mode }} • {{ $stream->bitrate_kbps }} kbps • {{ $stream->fps }} FPS</span>
        </div>
        <pre class="p-3 rounded-lg bg-black/90 border border-gray-800 text-emerald-400 font-mono text-[11px] leading-relaxed overflow-x-auto max-h-60 overflow-y-auto">{{ $logs }}</pre>
    </div>
</div>
