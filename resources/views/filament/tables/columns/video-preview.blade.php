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
@endphp

<div class="py-1">
    <div 
        class="relative group cursor-pointer inline-flex items-center justify-center overflow-hidden rounded-lg border border-slate-700/80 bg-slate-950 shadow-md hover:border-primary-500 hover:shadow-primary-500/20 hover:shadow-lg transition-all duration-200 {{ $isLandscape ? 'w-20 h-12' : 'w-14 h-20' }}"
        title="Videoyu izlemek için tıklayın"
    >
        @if($hasRealThumbnail)
            <img 
                src="{{ $thumbnailUrl }}" 
                alt="{{ $record->title }}"
                loading="lazy"
                class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105 pointer-events-none"
                onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='block';"
            />
        @endif

        @if($videoUrl)
            <video 
                src="{{ $videoUrl }}#t=0.5" 
                preload="metadata" 
                muted 
                playsinline
                style="{{ $hasRealThumbnail ? 'display: none;' : '' }}"
                class="w-full h-full object-cover pointer-events-none transition-transform duration-300 group-hover:scale-105"
                onloadeddata="window.captureVideoPreviewThumbnail && window.captureVideoPreviewThumbnail(this, {{ $record->id }})"
            ></video>
        @elseif(! $hasRealThumbnail)
            <div class="w-full h-full flex flex-col items-center justify-center bg-slate-900 text-slate-500">
                <svg class="w-6 h-6 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
            </div>
        @endif

        {{-- Play Icon Overlay --}}
        <div class="absolute inset-0 flex items-center justify-center bg-black/25 group-hover:bg-black/40 transition-colors pointer-events-none">
            <div class="w-6 h-6 rounded-full bg-black/60 backdrop-blur-xs flex items-center justify-center text-white/90 group-hover:text-white group-hover:scale-110 group-hover:bg-primary-600 transition-all shadow-md">
                <svg class="w-3 h-3 fill-current ml-0.5" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </div>
        </div>

        {{-- Duration Badge Overlay --}}
        @if($durationText && $durationText !== '00:00')
            <span class="absolute bottom-1 right-1 px-1 py-0.5 text-[9px] font-mono font-semibold rounded bg-black/80 text-white/95 backdrop-blur-xs pointer-events-none leading-none border border-white/10">
                {{ $durationText }}
            </span>
        @endif
    </div>
</div>

@once
<script>
    if (!window.captureVideoPreviewThumbnail) {
        window.captureVideoPreviewThumbnail = function(video, videoId) {
            if (!video || video.dataset.captured) return;
            video.dataset.captured = 'true';

            try {
                setTimeout(function() {
                    const canvas = document.createElement('canvas');
                    const w = video.videoWidth || 720;
                    const h = video.videoHeight || 1280;
                    canvas.width = Math.min(w, 480);
                    canvas.height = Math.round(canvas.width * (h / w));
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

                    fetch('/admin/videos/' + videoId + '/save-thumbnail', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            thumbnail: dataUrl,
                            duration: Math.round(video.duration || 0),
                            width: video.videoWidth || 0,
                            height: video.videoHeight || 0
                        })
                    }).catch(function() {});
                }, 300);
            } catch (e) {
                console.warn('Frame capture skipped:', e);
            }
        };
    }
</script>
@endonce
