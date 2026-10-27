@php
    $record = $getRecord();
    $video = $record?->video;
    $videoUrl = $video?->url;
    $thumbnailPath = $video?->thumbnail_path;
    $hasRealThumbnail = ! empty($thumbnailPath) && \Illuminate\Support\Facades\Storage::disk($video?->disk ?? 'public')->exists($thumbnailPath);
    $thumbnailUrl = $hasRealThumbnail ? asset('storage/' . ltrim($thumbnailPath, '/')) : null;
    $videoId = $video?->id;
@endphp

<div class="py-1">
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
</div>
