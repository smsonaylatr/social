@php
    $record = $getRecord();
    if (! $record) {
        return;
    }

    $videoUrl = $record->url;
    $thumbnailPath = $record->thumbnail_path;
    $hasRealThumbnail = ! empty($thumbnailPath) && \Illuminate\Support\Facades\Storage::disk($record->disk ?? 'public')->exists($thumbnailPath);
    $thumbnailUrl = $hasRealThumbnail ? asset('storage/' . ltrim($thumbnailPath, '/')) : null;
    $isLandscape = ($record->aspect_ratio === '16:9');
    $durationText = $record->formatted_duration;
    $videoId = $record->id;
@endphp

<div class="py-1">
    <div 
        class="video-preview-box relative group cursor-pointer inline-flex items-center justify-center overflow-hidden rounded-xl border border-slate-700/80 bg-slate-950 shadow-md hover:border-primary-500 hover:shadow-primary-500/20 transition-all duration-200 shrink-0 {{ $isLandscape ? 'is-landscape' : '' }}"
        style="{{ $isLandscape ? 'width: 96px !important; height: 60px !important; min-width: 96px !important; min-height: 60px !important; max-width: 96px !important; max-height: 60px !important;' : 'width: 60px !important; height: 90px !important; min-width: 60px !important; min-height: 90px !important; max-width: 60px !important; max-height: 90px !important;' }}"
        @if($videoId) data-video-id="{{ $videoId }}" data-video-url="{{ $videoUrl }}" @endif
        title="{{ $record->title }} (İzlemek için tıklayın)"
    >
        @if($hasRealThumbnail)
            <img 
                src="{{ $thumbnailUrl }}" 
                alt="{{ $record->title }}"
                loading="lazy"
                crossorigin="anonymous"
                style="width: 100% !important; height: 100% !important; max-width: 100% !important; max-height: 100% !important; object-fit: cover !important;"
                class="preview-thumb w-full h-full object-cover transition-transform duration-300 group-hover:scale-105 pointer-events-none"
                onerror="window.handlePreviewThumbError && window.handlePreviewThumbError(this, {{ $videoId ?? 0 }}, '{{ $videoUrl }}')"
                onload="window.verifyThumbnailQuality && window.verifyThumbnailQuality(this, {{ $videoId ?? 0 }}, '{{ $videoUrl }}')"
            />
        @elseif($videoUrl)
            <div 
                class="preview-thumb-container w-full h-full flex flex-col items-center justify-center bg-slate-900/90 text-slate-400 group-hover:bg-slate-900 transition-colors"
                data-needs-capture="true"
                data-video-id="{{ $videoId }}"
                data-video-url="{{ $videoUrl }}"
                style="width: 100% !important; height: 100% !important;"
            >
                <svg class="w-5 h-5 text-indigo-400/80 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
            </div>
        @else
            <div class="preview-fallback w-full h-full flex flex-col items-center justify-center bg-slate-900 text-slate-500" style="width: 100% !important; height: 100% !important;">
                <svg class="w-5 h-5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
            </div>
        @endif

        {{-- Play Icon Overlay (Touch-friendly & sleek on hover) --}}
        <div class="absolute inset-0 flex items-center justify-center bg-black/25 opacity-70 sm:opacity-0 sm:group-hover:opacity-100 sm:group-hover:bg-black/40 transition-all duration-200 pointer-events-none">
            <div class="w-6 h-6 rounded-full bg-primary-600/90 flex items-center justify-center text-white shadow-md transform scale-95 sm:scale-90 sm:group-hover:scale-100 transition-transform">
                <svg class="w-3 h-3 fill-current ml-0.5" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </div>
        </div>
    </div>
</div>
