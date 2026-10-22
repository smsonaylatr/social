<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <p class="text-xs text-gray-500">Sunucu, arka plan servisleri ve harici API entegrasyonlarının canlı sağlık durumları.</p>
            <div class="flex items-center gap-2">
                <button 
                    type="button" 
                    wire:click="refreshHealth"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 transition flex items-center gap-1.5"
                >
                    <x-heroicon-m-arrow-path class="w-4 h-4" />
                    <span>Yenile</span>
                </button>
                <button 
                    type="button" 
                    wire:click="clearSystemCache"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg bg-primary-600 hover:bg-primary-700 text-white transition flex items-center gap-1.5"
                >
                    <x-heroicon-m-trash class="w-4 h-4" />
                    <span>Önbelleği Temizle</span>
                </button>
            </div>
        </div>

        {{-- Temel Sistem Servisleri Kartları --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {{-- Veritabanı --}}
            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs">
                <div class="flex items-center justify-between mb-3">
                    <span class="font-bold text-sm text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <x-heroicon-m-circle-stack class="w-5 h-5 text-indigo-500" />
                        Veritabanı (MySQL)
                    </span>
                    @if($healthData['database']['status'])
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400">Çalışıyor</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-400">Hata</span>
                    @endif
                </div>
                <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                    <div>Yanıt Süresi: <strong>{{ $healthData['database']['latency'] }} ms</strong></div>
                    <div>Durum: {{ $healthData['database']['status'] ? 'Bağlantı aktif ve sorgular çalışıyor.' : $healthData['database']['error'] }}</div>
                </div>
            </div>

            {{-- Depolama --}}
            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs">
                <div class="flex items-center justify-between mb-3">
                    <span class="font-bold text-sm text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <x-heroicon-m-folder class="w-5 h-5 text-amber-500" />
                        Depolama (Storage)
                    </span>
                    @if($healthData['storage']['status'])
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400">Sağlıklı</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-400">Yazma Hatası</span>
                    @endif
                </div>
                <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                    <div>Disk Sürücüsü: <strong>{{ $healthData['storage']['driver'] }}</strong></div>
                    <div>Okuma/Yazma: Video ve thumbnail dizinleri erişilebilir.</div>
                </div>
            </div>

            {{-- Kuyruk & Workers --}}
            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs">
                <div class="flex items-center justify-between mb-3">
                    <span class="font-bold text-sm text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <x-heroicon-m-queue-list class="w-5 h-5 text-sky-500" />
                        Kuyruk Motoru (Queue)
                    </span>
                    @if($healthData['queue']['status'])
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400">Aktif</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400">Hatalı İşler Var</span>
                    @endif
                </div>
                <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                    <div>Bekleyen Görevler: <strong>{{ $healthData['queue']['pending'] }}</strong></div>
                    <div>Başarısız İşler: <strong>{{ $healthData['queue']['failed'] }}</strong></div>
                </div>
            </div>

            {{-- FFmpeg --}}
            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs">
                <div class="flex items-center justify-between mb-3">
                    <span class="font-bold text-sm text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <x-heroicon-m-film class="w-5 h-5 text-rose-500" />
                        FFmpeg & FFprobe
                    </span>
                    @if($healthData['ffmpeg']['status'])
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400">Hazır</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400">GD Fallback</span>
                    @endif
                </div>
                <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                    <div class="truncate">Sürüm: <strong>{{ $healthData['ffmpeg']['version'] }}</strong></div>
                    <div>İşlev: Otomatik kare yakalama & metadata analizi.</div>
                </div>
            </div>

            {{-- AI Motoru --}}
            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-xs">
                <div class="flex items-center justify-between mb-3">
                    <span class="font-bold text-sm text-gray-900 dark:text-gray-100 flex items-center gap-2">
                        <x-heroicon-m-sparkles class="w-5 h-5 text-amber-500" />
                        AI İçerik Motoru
                    </span>
                    @if($healthData['ai']['status'])
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400">Bağlı</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-400">Test / Şablon</span>
                    @endif
                </div>
                <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                    <div>Model: <strong>{{ $healthData['ai']['model'] }}</strong></div>
                    <div>Durum: {{ $healthData['ai']['status'] ? 'OpenAI GPT-4o API aktif.' : 'API anahtarı girildiğinde canlıya geçer.' }}</div>
                </div>
            </div>
        </div>

        {{-- 7 Platform Canlı Entegrasyon Tablosu --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-m-share class="w-5 h-5 text-primary-500" />
                    <span>Sosyal Medya Platform API Sağlık ve Hesap Envanteri</span>
                </div>
            </x-slot>

            <div class="divide-y divide-gray-100 dark:divide-gray-800 text-xs">
                @foreach($healthData['platforms'] as $platform)
                    <div class="py-3 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ $platform['name'] }}</span>
                            <span class="text-gray-500">Günlük Limit: {{ $platform['daily_post_limit'] }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            @if($platform['active_accounts_count'] > 0)
                                <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400 font-semibold">
                                    {{ $platform['active_accounts_count'] }} Aktif Hesap
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                    Hesap Eklenmedi
                                </span>
                            @endif
                            <a href="{{ url('/admin/social-accounts') }}" class="text-primary-600 hover:underline">Yapılandır</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
