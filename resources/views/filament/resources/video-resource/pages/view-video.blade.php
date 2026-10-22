<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Sol Kolon: Video Oynatıcı ve Teknik Özellikler --}}
        <div class="lg:col-span-1 space-y-6">
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-m-play class="w-5 h-5 text-primary-500" />
                        <span>Video Oynatıcı</span>
                    </div>
                </x-slot>

                <div class="rounded-xl overflow-hidden bg-black aspect-video flex items-center justify-center relative shadow-inner">
                    @if($record->url)
                        <video 
                            controls 
                            preload="metadata" 
                            poster="{{ $record->thumbnail_url }}" 
                            class="w-full h-full max-h-[480px] object-contain"
                        >
                            <source src="{{ $record->url }}" type="{{ $record->mime_type }}">
                            Tarayıcınız video etiketini desteklemiyor.
                        </video>
                    @else
                        <div class="p-8 text-center text-gray-400">
                            <x-heroicon-o-video-camera-slash class="w-12 h-12 mx-auto mb-2 opacity-50" />
                            <p class="text-sm">Video dosyası bulunamadı</p>
                        </div>
                    @endif
                </div>

                <div class="mt-4 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                    <span>En-Boy: <strong class="text-gray-800 dark:text-gray-200">{{ $record->aspect_ratio }}</strong></span>
                    <span>Süre: <strong class="text-gray-800 dark:text-gray-200">{{ $record->formatted_duration }}</strong></span>
                    <span>Boyut: <strong class="text-gray-800 dark:text-gray-200">{{ $record->formatted_file_size }}</strong></span>
                </div>
            </x-filament::section>

            {{-- Teknik Özellikler Kartı --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-m-cpu-chip class="w-5 h-5 text-primary-500" />
                        <span>Teknik Parametreler</span>
                    </div>
                </x-slot>

                <dl class="divide-y divide-gray-100 dark:divide-gray-800 text-xs">
                    <div class="py-2 flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Çözünürlük</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->width }} x {{ $record->height }} px</dd>
                    </div>
                    <div class="py-2 flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">FPS (Kare Hızı)</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->fps }} fps</dd>
                    </div>
                    <div class="py-2 flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">MIME Türü</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->mime_type }}</dd>
                    </div>
                    <div class="py-2 flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Yeniden Kullanım</dt>
                        <dd class="font-medium">
                            @if($record->is_reusable)
                                <span class="text-emerald-600 dark:text-emerald-400">✓ Uygun (Döngüde Tekrar Edebilir)</span>
                            @else
                                <span class="text-amber-600 dark:text-amber-400">✗ Tek Seferlik (Döngü Dışı)</span>
                            @endif
                        </dd>
                    </div>
                    <div class="py-2 flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Kullanım Sayısı</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $record->usage_count }} kez yayınlandı</dd>
                    </div>
                    <div class="py-2">
                        <dt class="text-gray-500 dark:text-gray-400 mb-1">SHA-256 Dosya Hash</dt>
                        <dd class="font-mono text-[10px] break-all bg-gray-50 dark:bg-gray-800 p-1.5 rounded text-gray-700 dark:text-gray-300">
                            {{ $record->file_hash ?: 'Hesaplanmadı' }}
                        </dd>
                    </div>
                </dl>
            </x-filament::section>
        </div>

        {{-- Sağ Kolon: Platform Uyumluluğu, İçerik & Yayınlar --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Platform Uyumluluk Kontrolleri --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-m-check-badge class="w-5 h-5 text-emerald-500" />
                        <span>Platform Uyumluluk Analizi</span>
                    </div>
                </x-slot>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach($platformCompatibilities as $platform => $result)
                        <div class="p-3 rounded-lg border {{ $result['compatible'] ? 'border-emerald-200 dark:border-emerald-950 bg-emerald-50/30 dark:bg-emerald-950/20' : 'border-rose-200 dark:border-rose-950 bg-rose-50/30 dark:bg-rose-950/20' }}">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-semibold text-xs capitalize text-gray-900 dark:text-gray-100">{{ $platform }}</span>
                                @if($result['compatible'])
                                    <span class="text-[10px] font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-900/40 px-1.5 py-0.5 rounded">Uyumlu</span>
                                @else
                                    <span class="text-[10px] font-medium text-rose-700 dark:text-rose-400 bg-rose-100 dark:bg-rose-900/40 px-1.5 py-0.5 rounded">Kısıtlı</span>
                                @endif
                            </div>
                            @if(!empty($result['warnings']))
                                <ul class="text-[11px] text-amber-700 dark:text-amber-400 list-disc list-inside mt-1">
                                    @foreach($result['warnings'] as $warning)
                                        <li>{{ $warning }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if(!empty($result['errors']))
                                <ul class="text-[11px] text-rose-700 dark:text-rose-400 list-disc list-inside mt-1">
                                    @foreach($result['errors'] as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if(empty($result['warnings']) && empty($result['errors']))
                                <p class="text-[11px] text-emerald-600 dark:text-emerald-400">Teknik limitlerle tam uyumlu.</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            {{-- İçerik, Hashtag & Ürün Tanımları --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-m-document-text class="w-5 h-5 text-primary-500" />
                        <span>İçerik ve SEO Bilgileri</span>
                    </div>
                </x-slot>

                <div class="space-y-4 text-xs">
                    <div>
                        <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-1">Özel Açıklama:</h4>
                        <p class="text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/60 p-3 rounded-lg leading-relaxed">
                            {{ $record->custom_caption ?: 'Özel bir açıklama girilmedi. Otomasyonda AI İçerik Stüdyosu tarafından üretilecek.' }}
                        </p>
                    </div>

                    @if(!empty($record->custom_hashtags))
                        <div>
                            <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-1">Özel Hashtag'ler:</h4>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($record->custom_hashtags as $tag)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-primary-50 text-primary-700 dark:bg-primary-950/60 dark:text-primary-300">
                                        #{{ ltrim($tag, '#') }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($record->product_url)
                        <div>
                            <h4 class="font-semibold text-gray-700 dark:text-gray-300 mb-1">Ürün / Hedef URL:</h4>
                            <a href="{{ $record->product_url }}" target="_blank" class="text-primary-600 dark:text-primary-400 hover:underline flex items-center gap-1 font-mono">
                                <span>{{ $record->product_url }}</span>
                                <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5 inline" />
                            </a>
                        </div>
                    @endif
                </div>
            </x-filament::section>

            {{-- Yayın Geçmişi ve Planlanan Paylaşımlar --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-heroicon-m-clock class="w-5 h-5 text-primary-500" />
                        <span>Yayın Geçmişi ve Hedefler</span>
                    </div>
                </x-slot>

                @php
                    $scheduledPosts = $record->scheduledPosts()->with(['targets.socialAccount'])->latest()->limit(5)->get();
                @endphp

                @if($scheduledPosts->isEmpty())
                    <p class="text-xs text-gray-500 dark:text-gray-400 py-3 text-center">
                        Bu video henüz hiçbir sosyal medya kanalında planlanmadı veya yayınlanmadı.
                    </p>
                @else
                    <div class="divide-y divide-gray-100 dark:divide-gray-800 text-xs">
                        @foreach($scheduledPosts as $post)
                            <div class="py-3 flex items-center justify-between">
                                <div>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $post->scheduled_at->format('d.m.Y H:i') }}</span>
                                    <span class="text-gray-500 ml-2">({{ $post->scheduled_at->diffForHumans() }})</span>
                                    <div class="flex gap-1 mt-1">
                                        @foreach($post->targets as $target)
                                            <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-800 uppercase font-mono">
                                                {{ $target->platform_code }}: {{ $target->status->label() }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                                <span class="px-2 py-1 rounded text-[11px] font-semibold bg-primary-100 dark:bg-primary-950 text-primary-700 dark:text-primary-300">
                                    {{ $post->status->label() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
