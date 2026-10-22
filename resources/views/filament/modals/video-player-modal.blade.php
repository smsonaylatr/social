@php
    $isVertical = ($video->aspect_ratio === '9:16' || ($video->height > $video->width));
@endphp

<div class="relative flex justify-center items-center -m-4">
    <div class="overflow-hidden bg-black rounded-2xl border border-slate-700/80 shadow-2xl relative flex items-center justify-center {{ $isVertical ? 'w-full max-w-[280px] sm:max-w-[310px] aspect-[9/16] max-h-[75vh]' : 'w-full max-w-xl aspect-video max-h-[60vh]' }}">
        
        {{-- Close Button (X) Top Right Corner --}}
        <button 
            type="button" 
            x-on:click="close()" 
            class="absolute top-2.5 right-2.5 z-30 w-8 h-8 rounded-full bg-black/60 hover:bg-black/90 text-white/80 hover:text-white flex items-center justify-center transition-all backdrop-blur-md border border-white/20 shadow-lg cursor-pointer group"
            title="Kapat"
        >
            <svg class="w-4 h-4 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

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
