<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Video Seçim ve Üretim Kartı --}}
        <x-filament::section>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex-1 max-w-md">
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">İçerik Üretilecek Video</label>
                    <select wire:model.live="selectedVideoId" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="">-- Bir Video Seçin --</option>
                        @foreach(\App\Models\Video::latest()->get() as $v)
                            <option value="{{ $v->id }}">{{ $v->title }} ({{ $v->formatted_duration }} - {{ $v->aspect_ratio }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-3">
                    <button 
                        type="button"
                        wire:click="generateAiContent"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg text-white bg-primary-600 hover:bg-primary-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:opacity-50 transition"
                    >
                        <x-heroicon-m-sparkles class="w-5 h-5 text-amber-300" />
                        <span wire:loading.remove>7 Platform İçin Akıllı İçerik Üret</span>
                        <span wire:loading>Yapay Zekâ Analiz Ediyor...</span>
                    </button>
                </div>
            </div>

            @if($this->selectedVideo)
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800 flex items-center gap-4 text-xs text-gray-600 dark:text-gray-400">
                    <span>Format: <strong class="text-gray-900 dark:text-gray-200">{{ $this->selectedVideo->aspect_ratio }}</strong></span>
                    <span>Süre: <strong class="text-gray-900 dark:text-gray-200">{{ $this->selectedVideo->formatted_duration }}</strong></span>
                    <span>Marka: <strong class="text-gray-900 dark:text-gray-200">{{ $this->selectedVideo->brand?->name ?? 'Patenli Ayakkabılar' }}</strong></span>
                    <span>Mevcut Durum: <strong class="text-emerald-600 dark:text-emerald-400">{{ $this->selectedVideo->status->label() }}</strong></span>
                </div>
            @endif
        </x-filament::section>

        @if($this->selectedVideoId)
            {{-- Platform Sekmeleri --}}
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 shadow-xs overflow-hidden">
                <div class="border-b border-gray-200 dark:border-gray-800 flex overflow-x-auto bg-gray-50/50 dark:bg-gray-950/40">
                    @php
                        $tabList = [
                            'instagram' => ['name' => 'Instagram Reels', 'icon' => 'heroicon-m-camera'],
                            'facebook' => ['name' => 'Facebook', 'icon' => 'heroicon-m-globe-alt'],
                            'youtube' => ['name' => 'YouTube Shorts', 'icon' => 'heroicon-m-play-circle'],
                            'x' => ['name' => 'X (Twitter)', 'icon' => 'heroicon-m-chat-bubble-bottom-center-text'],
                            'pinterest' => ['name' => 'Pinterest Pin', 'icon' => 'heroicon-m-bookmark'],
                            'tiktok' => ['name' => 'TikTok', 'icon' => 'heroicon-m-sparkles'],
                            'linkedin' => ['name' => 'LinkedIn Post', 'icon' => 'heroicon-m-briefcase'],
                        ];
                    @endphp

                    @foreach($tabList as $key => $tabInfo)
                        <button 
                            type="button"
                            wire:click="$set('activeTab', '{{ $key }}')"
                            class="px-4 py-3 text-xs font-semibold flex items-center gap-2 border-b-2 whitespace-nowrap transition {{ $activeTab === $key ? 'border-primary-600 text-primary-600 dark:text-primary-400 bg-white dark:bg-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 hover:border-gray-300' }}"
                        >
                            <span>{{ $tabInfo['name'] }}</span>
                            @if(!empty($platformContents[$key]['id']))
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- Aktif Platform İçerik Formu --}}
                <div class="p-6 space-y-4">
                    @php
                        $cur = $platformContents[$activeTab] ?? null;
                        $charCount = mb_strlen($cur['caption'] ?? '');
                        $maxChar = match($activeTab) {
                            'x' => 280,
                            'pinterest' => 500,
                            'instagram', 'tiktok' => 2200,
                            'linkedin' => 3000,
                            'youtube' => 5000,
                            default => 60000,
                        };
                    @endphp

                    @if(!empty($cur['ai_model']) && str_contains($cur['ai_model'], 'test-mode'))
                        <div class="p-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 text-amber-800 dark:text-amber-300 text-xs flex items-center gap-2">
                            <x-heroicon-m-beaker class="w-4 h-4 text-amber-500 shrink-0" />
                            <span><strong>Test Modu / Şablon Çıktısı:</strong> OpenAI API anahtarı girilene kadar akıllı şablon motoru çalışmaktadır.</span>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Platform Başlığı</label>
                            <input 
                                type="text" 
                                wire:model="platformContents.{{ $activeTab }}.title" 
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="Başlık girin..."
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Dikkat Çekici Giriş Cümlesi (Hook)</label>
                            <input 
                                type="text" 
                                wire:model="platformContents.{{ $activeTab }}.hook_sentence" 
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="İlk 3 saniye kancası..."
                            />
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300">Açıklama Metni (Caption)</label>
                            <span class="text-xs {{ $charCount > $maxChar ? 'text-rose-600 font-bold' : 'text-gray-400' }}">
                                {{ $charCount }} / {{ $maxChar }} karakter
                            </span>
                        </div>
                        <textarea 
                            rows="6" 
                            wire:model="platformContents.{{ $activeTab }}.caption"
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                            placeholder="Açıklama metni..."
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Hashtag'ler</label>
                            <input 
                                type="text" 
                                wire:model="platformContents.{{ $activeTab }}.hashtags" 
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="#patenliayakkabi #walkrollfly"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Call To Action (CTA)</label>
                            <input 
                                type="text" 
                                wire:model="platformContents.{{ $activeTab }}.call_to_action" 
                                class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                placeholder="Hemen web sitemizi ziyaret edin..."
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Hedef Ürün / Landing URL</label>
                        <input 
                            type="url" 
                            wire:model="platformContents.{{ $activeTab }}.suggested_product_url" 
                            class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 font-mono text-xs"
                            placeholder="https://patenliayakkabilar.com"
                        />
                    </div>

                    <div class="pt-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-gray-600 dark:text-gray-400">Onay Durumu:</label>
                            <select wire:model="platformContents.{{ $activeTab }}.status" class="text-xs rounded border-gray-300 dark:border-gray-700 dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                                <option value="draft">Taslak</option>
                                <option value="approved">Onaylandı (Yayına Hazır)</option>
                                <option value="rejected">Reddedildi</option>
                            </select>
                        </div>

                        <button 
                            type="button"
                            wire:click="savePlatformContent('{{ $activeTab }}')"
                            class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg text-white bg-emerald-600 hover:bg-emerald-700 shadow-sm transition"
                        >
                            <x-heroicon-m-check class="w-4 h-4" />
                            <span>{{ ucfirst($activeTab) }} İçeriğini Kaydet</span>
                        </button>
                    </div>
                </div>
            </div>
        @else
            <x-filament::section>
                <div class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm">
                    <x-heroicon-o-film class="w-12 h-12 mx-auto mb-2 opacity-50" />
                    <p>AI İçerik Stüdyosu'nda metin üretmek için lütfen yukarıdan bir video seçin veya Video Havuzuna yeni bir video yükleyin.</p>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
