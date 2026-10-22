<div class="space-y-4">
    <div class="rounded-xl overflow-hidden bg-black aspect-video flex items-center justify-center relative shadow-lg">
        @if($video->url)
            <video 
                controls 
                autoplay 
                preload="metadata" 
                poster="{{ $video->thumbnail_url }}" 
                class="w-full h-full max-h-[500px] object-contain"
            >
                <source src="{{ $video->url }}" type="{{ $video->mime_type }}">
                Tarayıcınız video oynatmayı desteklemiyor.
            </video>
        @else
            <div class="p-8 text-center text-gray-400">
                <x-heroicon-o-video-camera-slash class="w-12 h-12 mx-auto mb-2 opacity-50" />
                <p class="text-sm">Video dosyası bulunamadı</p>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs bg-gray-50 dark:bg-gray-900 p-3 rounded-xl border border-gray-100 dark:border-gray-800">
        <div>
            <span class="text-gray-500 block">Süre:</span>
            <strong class="text-gray-800 dark:text-gray-200 text-sm">{{ $video->formatted_duration }}</strong>
        </div>
        <div>
            <span class="text-gray-500 block">Çözünürlük & Format:</span>
            <strong class="text-gray-800 dark:text-gray-200 text-sm">{{ $video->width }}x{{ $video->height }} ({{ $video->aspect_ratio }})</strong>
        </div>
        <div>
            <span class="text-gray-500 block">Dosya Boyutu:</span>
            <strong class="text-gray-800 dark:text-gray-200 text-sm">{{ $video->formatted_file_size }}</strong>
        </div>
        <div>
            <span class="text-gray-500 block">Yayın Sayısı:</span>
            <strong class="text-gray-800 dark:text-gray-200 text-sm">{{ $video->usage_count }} kez yayınlandı</strong>
        </div>
    </div>
</div>
