<div class="space-y-4 text-xs">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800 gap-2">
        <div>
            <h3 class="font-bold text-sm text-gray-900 dark:text-gray-100 flex items-center gap-2">
                <span>{{ $post->post_type === 'text' ? '📝 Metin Gönderisi (Videosuz)' . ($post->video?->title ? ' - ' . $post->video->title : '') : ($post->video?->title ?? 'İsimsiz Video') }}</span>
                @if($post->post_type === 'text')
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-300">
                        Metin Formatı
                    </span>
                @elseif($post->playlist)
                    <span class="text-[11px] font-normal px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                        {{ $post->playlist->name }}
                    </span>
                @endif
            </h3>
            <p class="text-gray-500 mt-0.5">
                Planlanan Tarih: {{ $post->scheduled_at ? $post->scheduled_at->format('d.m.Y H:i:s') : 'Belirtilmemiş' }}
                @if($post->scheduled_at)
                    ({{ $post->scheduled_at->diffForHumans() }})
                @endif
            </p>
        </div>
        <div>
            <span class="px-2.5 py-1 rounded text-xs font-semibold bg-primary-100 dark:bg-primary-950 text-primary-700 dark:text-primary-300">
                {{ $post->status->label() }}
            </span>
        </div>
    </div>

    {{-- Platform Hedefleri ve Açıklamalar --}}
    <div class="space-y-4">
        <h4 class="font-semibold text-gray-800 dark:text-gray-200 text-sm flex items-center gap-1.5">
            <x-heroicon-m-share class="w-4 h-4 text-primary-500" />
            <span>Platform Hedefleri & Paylaşılan İçerik Detayları:</span>
        </h4>

        @forelse($post->targets as $target)
            @php
                $targetCaption = $target->caption ?: ($post->video?->custom_caption ?: ($post->video?->description ?: null));
                $hasHashtags = ! empty($target->hashtags) && is_array($target->hashtags);
            @endphp
            <div class="scheduled-target-card p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/80 space-y-3">
                {{-- Platform Header & Status --}}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                    <div class="flex items-center gap-2">
                        <span class="font-bold capitalize text-gray-900 dark:text-gray-100 text-sm flex items-center gap-1.5">
                            <span class="inline-block w-2.5 h-2.5 rounded-full {{ $target->status->value === 'published' ? 'bg-emerald-500' : ($target->status->value === 'failed' ? 'bg-rose-500' : 'bg-sky-500') }}"></span>
                            {{ $target->platform_code }}
                        </span>
                        <span class="text-gray-500 dark:text-gray-400 text-xs font-medium truncate max-w-[200px]">
                            ({{ $target->socialAccount?->name ?? 'Hesap' }})
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded text-[11px] font-semibold 
                            {{ $target->status->value === 'published' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400' : '' }}
                            {{ $target->status->value === 'failed' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-400' : '' }}
                            {{ in_array($target->status->value, ['scheduled', 'queued']) ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-400' : '' }}
                            {{ in_array($target->status->value, ['uploading', 'processing', 'retrying']) ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400' : '' }}
                        ">
                            {{ $target->status->label() }}
                        </span>

                        @if($target->external_post_url)
                            <a href="{{ $target->external_post_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs font-semibold bg-primary-600 hover:bg-primary-700 text-white shadow-sm transition">
                                <span>Canlı Gönderi</span>
                                <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5" />
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Paylaşılan Başlık (varsa) --}}
                @if($target->title && $target->title !== $post->video?->title)
                    <div class="px-3 py-1.5 rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                        <span class="text-gray-500 text-[11px] font-semibold block">Platform Başlığı:</span>
                        <span class="text-gray-800 dark:text-gray-200 font-medium text-xs">{{ $target->title }}</span>
                    </div>
                @endif

                {{-- Paylaşılan Açıklama / Metin (Caption) --}}
                <div x-data="{ copied: false }" class="rounded-lg bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-3 space-y-2">
                    <div class="flex items-center justify-between text-gray-600 dark:text-gray-400 border-b border-gray-100 dark:border-gray-800 pb-1.5">
                        <span class="font-semibold text-[11px] flex items-center gap-1 text-gray-700 dark:text-gray-300">
                            <x-heroicon-m-document-text class="w-3.5 h-3.5 text-primary-500" />
                            Paylaşılan Gönderi Açıklaması:
                        </span>
                        @if($targetCaption)
                            <button 
                                type="button" 
                                @click="navigator.clipboard.writeText({{ json_encode($targetCaption) }}); copied = true; setTimeout(() => copied = false, 2000)" 
                                class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded font-medium transition hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                :class="copied ? '!text-emerald-600 dark:!text-emerald-400' : ''"
                            >
                                <template x-if="!copied">
                                    <span class="flex items-center gap-1">
                                        <x-heroicon-m-clipboard-document class="w-3.5 h-3.5" />
                                        <span>Metni Kopyala</span>
                                    </span>
                                </template>
                                <template x-if="copied">
                                    <span class="flex items-center gap-1 font-semibold">
                                        <x-heroicon-m-check class="w-3.5 h-3.5" />
                                        <span>Kopyalandı!</span>
                                    </span>
                                </template>
                            </button>
                        @endif
                    </div>

                    @if($targetCaption)
                        <div class="text-gray-800 dark:text-gray-200 text-xs whitespace-pre-line leading-relaxed select-text font-normal max-h-64 overflow-y-auto pr-1">
                            {{ $targetCaption }}
                        </div>
                    @else
                        <div class="text-gray-400 italic text-xs py-1">
                            Bu platform hedefi için özel açıklama metni tanımlanmamış.
                        </div>
                    @endif

                    {{-- Hashtagler --}}
                    @if($hasHashtags)
                        <div class="pt-2 border-t border-gray-100 dark:border-gray-800 flex flex-wrap gap-1 items-center">
                            <span class="text-gray-500 text-[10px] font-semibold mr-1">Hashtagler:</span>
                            @foreach($target->hashtags as $tag)
                                @php
                                    $cleanTag = '#' . ltrim(trim($tag), '#');
                                @endphp
                                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-mono font-medium bg-primary-50 dark:bg-primary-950/60 text-primary-700 dark:text-primary-300 border border-primary-200/50 dark:border-primary-800/50">
                                    {{ $cleanTag }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    {{-- Ürün Linki --}}
                    @if($target->product_url)
                        <div class="pt-1.5 text-[11px] text-gray-500 flex items-center gap-1">
                            <span class="font-semibold text-gray-600 dark:text-gray-400">Ürün / Hedef Link:</span>
                            <a href="{{ $target->product_url }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 dark:text-primary-400 hover:underline truncate">
                                {{ $target->product_url }}
                            </a>
                        </div>
                    @endif
                </div>

                {{-- İstatistikler ve Teknik Bilgiler --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-gray-600 dark:text-gray-400 pt-1 font-mono text-[11px]">
                    <div>Deneme Sayısı: <strong>{{ $target->attempt_count }}</strong></div>
                    <div>Hedef Yayın Saati: <strong>{{ $target->scheduled_at ? $target->scheduled_at->format('d.m.Y H:i') : ($post->scheduled_at ? $post->scheduled_at->format('d.m.Y H:i') : '-') }}</strong></div>
                    <div>Gerçekleşen Yayın: {{ $target->published_at ? $target->published_at->format('d.m.Y H:i') : '-' }}</div>
                    <div class="col-span-1 sm:col-span-2 break-all">İzleme Kimliği: {{ $target->correlation_id }}</div>
                </div>

                {{-- Hata Mesajı --}}
                @if($target->error_message)
                    @php
                        $msg = $target->error_message;
                        $isUploadLimit = str_contains($msg, 'uploadLimitExceeded') || str_contains($msg, 'exceeded the number of videos');
                    @endphp
                    <div class="mt-2 p-2.5 rounded-lg bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 break-words leading-relaxed text-[11px]">
                        @if($isUploadLimit)
                            <div class="flex items-start gap-1.5 font-sans">
                                <span class="text-amber-500 font-bold text-xs shrink-0">⚠️</span>
                                <div>
                                    <strong class="font-semibold text-rose-800 dark:text-rose-200">YouTube Kanal Yükleme Limiti Aşıldı (uploadLimitExceeded):</strong><br>
                                    <span>YouTube kanallarında 24 saatlik güvenlik yükleme kotası dolduğunda bu durum oluşur. "Başarısızları Kuyruğa Al" butonuna basarak bu hedefi yeniden kuyruğa alabilirsiniz; kota yenilendiğinde otomatik olarak yayınlanacaktır.</span>
                                </div>
                            </div>
                        @else
                            <strong>Hata:</strong> {{ $msg }}
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 text-center text-gray-500">
                <p>Henüz tanımlanmış bir platform hedefi bulunmuyor.</p>
                @if($post->video?->custom_caption || $post->video?->description)
                    <div class="mt-3 text-left p-3 bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                        <span class="font-semibold text-gray-700 dark:text-gray-300 text-xs block mb-1">Video Açıklaması:</span>
                        <p class="text-gray-800 dark:text-gray-200 whitespace-pre-line text-xs">
                            {{ $post->video->custom_caption ?: $post->video->description }}
                        </p>
                    </div>
                @endif
            </div>
        @endforelse
    </div>
</div>
