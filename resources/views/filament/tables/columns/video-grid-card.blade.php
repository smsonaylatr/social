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
    $fileSizeText = $record->formatted_file_size;
    $usageCount = $record->usage_count ?? 0;
    $title = $record->title ?: 'Video #' . $record->id;
    $aspectRatio = $record->aspect_ratio ?: '9:16';
@endphp

<div class="video-grid-card flex flex-col w-full group/card select-none text-left">
    {{-- Thumbnail / Media Container --}}
    <div 
        class="relative w-full overflow-hidden rounded-xl bg-slate-950 border border-slate-800/80 shadow-md group-hover/card:border-indigo-500/60 transition-all duration-300 {{ $isLandscape ? 'aspect-video' : 'aspect-[9/14]' }}"
    >
        @if($hasRealThumbnail)
            <img 
                src="{{ $thumbnailUrl }}" 
                alt="{{ $title }}"
                loading="lazy"
                class="w-full h-full object-cover transition-transform duration-500 group-hover/card:scale-105 pointer-events-none"
                onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='block';"
            />
        @endif

        @if($videoUrl)
            <video 
                src="{{ $videoUrl }}#t=0.5" 
                preload="metadata" 
                muted 
                playsinline
                style="{{ $hasRealThumbnail ? 'display: none !important;' : 'display: block !important;' }}"
                class="w-full h-full object-cover pointer-events-none transition-transform duration-500 group-hover/card:scale-105"
            ></video>
        @elseif(! $hasRealThumbnail)
            <div class="w-full h-full flex flex-col items-center justify-center bg-slate-900/90 text-slate-500">
                <svg class="w-10 h-10 opacity-40 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
                <span class="text-xs text-slate-500 font-medium">Önizleme Yok</span>
            </div>
        @endif

        {{-- Gradient Overlays for readable badges --}}
        <div class="absolute inset-x-0 top-0 h-14 bg-gradient-to-b from-black/70 via-black/25 to-transparent pointer-events-none"></div>
        <div class="absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-black/80 via-black/30 to-transparent pointer-events-none"></div>

        {{-- Top Badges: Format & Ready Status --}}
        <div class="absolute top-2.5 inset-x-2.5 flex items-center justify-between pointer-events-none z-10">
            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold tracking-wider uppercase backdrop-blur-md {{ $aspectRatio === '9:16' ? 'bg-emerald-500/25 text-emerald-300 border border-emerald-500/30' : ($aspectRatio === '16:9' ? 'bg-indigo-500/25 text-indigo-300 border border-indigo-500/30' : 'bg-slate-700/60 text-slate-300 border border-slate-600/40') }}">
                {{ $aspectRatio }}
            </span>

            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10px] font-medium backdrop-blur-md bg-black/60 text-emerald-400 border border-emerald-500/20 shadow-sm">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                Yayına Hazır
            </span>
        </div>

        {{-- Center Play Icon Overlay --}}
        <div class="absolute inset-0 flex items-center justify-center bg-black/35 backdrop-blur-[1px] opacity-0 group-hover/card:opacity-100 transition-opacity duration-300 pointer-events-none z-10">
            <div class="w-12 h-12 rounded-full bg-indigo-600/90 text-white flex items-center justify-center shadow-lg shadow-indigo-600/50 transform group-hover/card:scale-110 transition-transform duration-200">
                <svg class="w-6 h-6 fill-current translate-x-0.5" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </div>
        </div>

        {{-- Bottom Right: Duration Badge --}}
        <div class="absolute bottom-2.5 right-2.5 pointer-events-none z-10">
            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-mono font-medium backdrop-blur-md bg-black/85 text-white/90 shadow border border-white/10">
                {{ $durationText }}
            </span>
        </div>
    </div>

    {{-- Info Card Section --}}
    <div class="pt-3 px-1 flex flex-col gap-1.5">
        <h4 
            class="text-sm font-semibold text-slate-200 group-hover/card:text-indigo-400 transition-colors line-clamp-1 truncate"
            title="{{ $title }}"
        >
            {{ $title }}
        </h4>

        <div class="flex items-center justify-between text-xs text-slate-400 border-t border-slate-800/80 pt-2 mt-0.5">
            <span class="inline-flex items-center gap-1 text-slate-400 font-medium">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                {{ $fileSizeText }}
            </span>

            <span class="inline-flex items-center gap-1 {{ $usageCount > 0 ? 'text-indigo-400 font-semibold' : 'text-slate-500' }}">
                <svg class="w-3.5 h-3.5 {{ $usageCount > 0 ? 'text-indigo-400' : 'text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                </svg>
                {{ $usageCount > 0 ? "{$usageCount} kez" : '0 kez' }}
            </span>
        </div>
    </div>
</div>
