@php
    $record = $getRecord();
    $isText = $record?->post_type === 'text';
    $video = $record?->video;
    $videoUrl = $video?->url;
    $thumbnailPath = $video?->thumbnail_path;
    $hasRealThumbnail = ! empty($thumbnailPath) && \Illuminate\Support\Facades\Storage::disk($video?->disk ?? 'public')->exists($thumbnailPath);
    $thumbnailUrl = $hasRealThumbnail ? asset('storage/' . ltrim($thumbnailPath, '/')) : null;
    $videoId = $video?->id;
@endphp

<div class="py-1">
    @if($isText)
        <div 
            class="scheduled-preview-box relative group cursor-pointer inline-flex flex-col items-center justify-center overflow-hidden rounded-xl border border-sky-500/40 bg-gradient-to-br from-slate-900 via-sky-950/60 to-slate-900 shadow-md hover:scale-105 hover:border-sky-400 transition-all duration-200 shrink-0 p-2 text-center select-none"
            style="width: 70px !important; height: 105px !important; min-width: 70px !important; min-height: 105px !important; max-width: 70px !important; max-height: 105px !important; border-radius: 12px;"
            title="Metin Gönderisi (Videosuz - Detaylar için tıklayın)"
        >
            <div class="w-8 h-8 rounded-full bg-sky-500/20 text-sky-400 flex items-center justify-center mb-1 ring-1 ring-sky-400/30 group-hover:scale-110 transition-transform">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </div>
            <span class="text-[10px] font-bold tracking-wider text-sky-300 uppercase">METİN</span>
            <span class="text-[8px] text-slate-400 mt-0.5">Videosuz</span>
        </div>
    @else
        <div 
            class="video-preview-box scheduled-preview-box relative group cursor-pointer inline-flex items-center justify-center overflow-hidden rounded-xl border border-slate-700/60 bg-slate-950 shadow-md hover:scale-105 hover:border-primary-500 transition-all duration-200 shrink-0"
            style="width: 70px !important; height: 105px !important; min-width: 70px !important; min-height: 105px !important; max-width: 70px !important; max-height: 105px !important; border-radius: 12px;"
            @if($videoId) data-video-id="{{ $videoId }}" data-video-url="{{ $videoUrl }}" @endif
            title="{{ $video?->title ?? 'Önizleme' }} (İzlemek için tıklayın)"
        >
            @if($hasRealThumbnail)
                <img 
                    src="{{ $thumbnailUrl }}" 
                    alt="{{ $video?->title ?? 'Video' }}"
                    loading="lazy"
                    crossorigin="anonymous"
                    class="preview-thumb w-full h-full object-cover pointer-events-none transition-transform duration-200 group-hover:scale-105"
                    style="width: 70px !important; height: 105px !important; min-width: 70px !important; min-height: 105px !important; object-fit: cover !important; display: block;"
                    onerror="window.handlePreviewThumbError && window.handlePreviewThumbError(this, {{ $videoId ?? 0 }}, '{{ $videoUrl }}')"
                    onload="window.verifyThumbnailQuality && window.verifyThumbnailQuality(this, {{ $videoId ?? 0 }}, '{{ $videoUrl }}')"
                />
            @elseif($videoUrl)
                <div 
                    class="preview-thumb-container w-full h-full flex flex-col items-center justify-center bg-slate-900/90 text-slate-400 group-hover:bg-slate-900 transition-colors"
                    data-needs-capture="true"
                    data-video-id="{{ $videoId }}"
                    data-video-url="{{ $videoUrl }}"
                    style="width: 70px !important; height: 105px !important;"
                >
                    <svg class="w-5 h-5 text-indigo-400/80 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <span class="text-[9px] text-slate-500 mt-1 font-mono tracking-tight">Önizleme...</span>
                </div>
            @else
                <div class="preview-fallback w-full h-full flex flex-col items-center justify-center bg-slate-900 text-slate-500" style="width: 70px !important; height: 105px !important;">
                    <svg class="w-6 h-6 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </div>
            @endif

            {{-- Play Icon Overlay --}}
            <div class="absolute inset-0 flex items-center justify-center bg-black/25 opacity-70 group-hover:opacity-100 group-hover:bg-black/40 transition-all duration-200 pointer-events-none">
                <div class="w-6 h-6 rounded-full bg-primary-600/90 flex items-center justify-center text-white shadow-md transform scale-90 group-hover:scale-105 transition-transform">
                    <svg class="w-3 h-3 fill-current ml-0.5" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </div>
            </div>
        </div>
    @endif
</div>
