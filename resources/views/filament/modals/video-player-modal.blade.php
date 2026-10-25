@php
    $isVertical = ($video->aspect_ratio === '9:16' || ($video->height > $video->width));
@endphp

<div class="flex flex-col justify-center items-center py-1">
    <div class="overflow-hidden bg-black rounded-2xl border border-slate-700/70 shadow-2xl relative flex items-center justify-center {{ $isVertical ? 'w-full max-w-[270px] sm:max-w-[300px] aspect-[9/16] max-h-[62vh]' : 'w-full max-w-[92vw] sm:max-w-[440px] aspect-video max-h-[48vh]' }}">
        @if($video->url)
            <video 
                controls 
                autoplay 
                playsinline
                preload="metadata" 
                poster="{{ $video->thumbnail_url }}" 
                class="w-full h-full object-contain bg-black"
            >
                <source src="{{ $video->url }}" type="{{ $video->mime_type ?: 'video/mp4' }}">
                Tarayıcınız video oynatmayı desteklemiyor.
            </video>
        @else
            <div class="p-6 text-center text-gray-400">
                <svg class="w-10 h-10 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
                <p class="text-xs">Video dosyası bulunamadı</p>
            </div>
        @endif
    </div>

    {{-- Mobil & Masaüstü Kompakt Bilgi Şeridi --}}
    <div class="mt-3 flex flex-wrap items-center justify-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium">
            📐 {{ $video->aspect_ratio ?: 'Format bilinmiyor' }}
        </span>
        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 font-medium">
            ⏱️ {{ $video->formatted_duration }}
        </span>
        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-medium">
            💾 {{ $video->formatted_file_size }}
        </span>
        @if($video->width && $video->height)
            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-mono">
                {{ $video->width }}x{{ $video->height }}
            </span>
        @endif
        @if($video->usage_count > 0)
            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 font-medium">
                🚀 {{ $video->usage_count }} yayın
            </span>
        @endif
    </div>
</div>
