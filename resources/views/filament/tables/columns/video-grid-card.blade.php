@php
    $record = $getRecord();
    if (! $record) {
        return;
    }

    $videoUrl = $record->url;
    $thumbnailPath = $record->thumbnail_path;
    $hasRealThumbnail = ! empty($thumbnailPath) && \Illuminate\Support\Facades\Storage::disk($record->disk ?? 'public')->exists($thumbnailPath);
    $thumbnailUrl = $hasRealThumbnail ? asset('storage/' . ltrim($thumbnailPath, '/')) : null;
    $durationText = $record->formatted_duration;
    $title = $record->title ?: 'Video #' . $record->id;
@endphp

<div 
    class="video-grid-square relative w-full h-full aspect-square overflow-hidden rounded-xl bg-slate-950 group select-none cursor-pointer border border-slate-800/80 hover:border-indigo-500/80 transition-all duration-300"
    style="aspect-ratio: 1 / 1 !important; width: 100% !important; height: 100% !important;"
    title="{{ $title }} (İzlemek için tıklayın)"
>
    @if($hasRealThumbnail)
        <img 
            src="{{ $thumbnailUrl }}" 
            alt="{{ $title }}"
            loading="lazy"
            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105 pointer-events-none"
            style="width: 100% !important; height: 100% !important; object-fit: cover !important; aspect-ratio: 1 / 1 !important; display: block !important;"
            onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='block';"
        />
    @endif

    @if($videoUrl)
        <video 
            src="{{ $videoUrl }}#t=0.5" 
            preload="metadata" 
            muted 
            playsinline
            style="{{ $hasRealThumbnail ? 'display: none !important;' : 'display: block !important;' }} width: 100% !important; height: 100% !important; object-fit: cover !important; aspect-ratio: 1 / 1 !important;"
            class="w-full h-full object-cover pointer-events-none transition-transform duration-500 group-hover:scale-105"
        ></video>
    @elseif(! $hasRealThumbnail)
        <div class="w-full h-full flex flex-col items-center justify-center bg-slate-900 text-slate-500" style="aspect-ratio: 1 / 1 !important;">
            <svg class="w-8 h-8 opacity-40 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
            </svg>
            <span class="text-[10px] text-slate-500">Önizleme Yok</span>
        </div>
    @endif

    {{-- Center Play Icon on Hover --}}
    <div class="absolute inset-0 flex items-center justify-center bg-black/40 backdrop-blur-[1px] opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10">
        <div class="w-11 h-11 rounded-full bg-indigo-600/90 text-white flex items-center justify-center shadow-lg shadow-indigo-600/50 transform group-hover:scale-110 transition-transform duration-200">
            <svg class="w-5 h-5 fill-current translate-x-0.5" viewBox="0 0 24 24">
                <path d="M8 5v14l11-7z"/>
            </svg>
        </div>
    </div>

    {{-- Duration Badge in bottom-right --}}
    @if($durationText)
        <div class="absolute bottom-2 right-2 pointer-events-none z-10">
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-medium backdrop-blur-md bg-black/80 text-white/90 shadow border border-white/10">
                {{ $durationText }}
            </span>
        </div>
    @endif
</div>
