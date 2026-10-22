@php
    $isVertical = ($video->aspect_ratio === '9:16' || ($video->height > $video->width));
@endphp

<div class="flex justify-center items-center py-0.5">
    <div class="overflow-hidden bg-black rounded-xl border border-slate-700/70 shadow-xl relative flex items-center justify-center {{ $isVertical ? 'w-full max-w-[210px] sm:max-w-[230px] aspect-[9/16] max-h-[48vh]' : 'w-full max-w-[320px] aspect-video max-h-[38vh]' }}">
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
</div>
