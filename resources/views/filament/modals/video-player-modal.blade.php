@php
    $isVertical = ($video->aspect_ratio === '9:16' || ($video->height > $video->width));
@endphp

<div class="space-y-4">
    <div class="flex justify-center items-center py-1">
        <div class="overflow-hidden bg-black rounded-2xl border border-slate-700/80 shadow-2xl relative flex items-center justify-center {{ $isVertical ? 'w-full max-w-[280px] sm:max-w-[310px] aspect-[9/16] max-h-[65vh]' : 'w-full max-w-xl aspect-video max-h-[55vh]' }}">
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
                <div class="p-8 text-center text-gray-400">
                    <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <p class="text-sm">Video dosyası bulunamadı</p>
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs bg-gray-50 dark:bg-gray-900/80 p-3 rounded-xl border border-gray-100 dark:border-gray-800">
        <div class="text-center p-1.5 rounded-lg bg-white/50 dark:bg-white/5">
            <span class="text-gray-500 dark:text-gray-400 block text-[11px]">Süre</span>
            <strong class="text-gray-800 dark:text-gray-200 text-xs font-mono">{{ $video->formatted_duration }}</strong>
        </div>
        <div class="text-center p-1.5 rounded-lg bg-white/50 dark:bg-white/5">
            <span class="text-gray-500 dark:text-gray-400 block text-[11px]">Format</span>
            <strong class="text-gray-800 dark:text-gray-200 text-xs font-mono">{{ $video->aspect_ratio }} ({{ $video->width }}x{{ $video->height }})</strong>
        </div>
        <div class="text-center p-1.5 rounded-lg bg-white/50 dark:bg-white/5">
            <span class="text-gray-500 dark:text-gray-400 block text-[11px]">Dosya Boyutu</span>
            <strong class="text-gray-800 dark:text-gray-200 text-xs">{{ $video->formatted_file_size }}</strong>
        </div>
        <div class="text-center p-1.5 rounded-lg bg-white/50 dark:bg-white/5">
            <span class="text-gray-500 dark:text-gray-400 block text-[11px]">Yayın Durumu</span>
            <strong class="text-emerald-500 dark:text-emerald-400 text-xs">{{ $video->usage_count > 0 ? $video->usage_count . ' kez' : 'Yayına Hazır' }}</strong>
        </div>
    </div>
</div>
