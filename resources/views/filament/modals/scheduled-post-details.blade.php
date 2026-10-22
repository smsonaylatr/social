<div class="space-y-4 text-xs">
    <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
        <div>
            <h3 class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ $post->video->title }}</h3>
            <p class="text-gray-500">Planlanan Tarih: {{ $post->scheduled_at->format('d.m.Y H:i:s') }}</p>
        </div>
        <span class="px-2.5 py-1 rounded text-xs font-semibold bg-primary-100 dark:bg-primary-950 text-primary-700 dark:text-primary-300">
            {{ $post->status->label() }}
        </span>
    </div>

    <div class="space-y-3">
        <h4 class="font-semibold text-gray-700 dark:text-gray-300">Platform Hedefleri ve İşlem Durumu:</h4>

        @foreach($post->targets as $target)
            <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-900/60">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-bold capitalize text-gray-900 dark:text-gray-100 text-sm">
                        {{ $target->platform_code }} ({{ $target->socialAccount->name }})
                    </span>
                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold 
                        {{ $target->status->value === 'published' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400' : '' }}
                        {{ $target->status->value === 'failed' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-400' : '' }}
                        {{ in_array($target->status->value, ['scheduled', 'queued']) ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-400' : '' }}
                    ">
                        {{ $target->status->label() }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-2 text-gray-600 dark:text-gray-400 mb-2 font-mono text-[11px]">
                    <div>Deneme Sayısı: <strong>{{ $target->attempt_count }}</strong></div>
                    <div>Yayın Zamanı: {{ $target->published_at ? $target->published_at->format('d.m.Y H:i') : '-' }}</div>
                    <div class="col-span-2 truncate">İzleme Kimliği: {{ $target->correlation_id }}</div>
                </div>

                @if($target->external_post_url)
                    <div class="mt-2">
                        <a href="{{ $target->external_post_url }}" target="_blank" class="text-primary-600 dark:text-primary-400 hover:underline flex items-center gap-1 font-semibold">
                            <span>Canlı Gönderiyi Aç</span>
                            <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5" />
                        </a>
                    </div>
                @endif

                @if($target->error_message)
                    <div class="mt-2 p-2 rounded bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300">
                        <strong>Hata:</strong> {{ $target->error_message }}
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
