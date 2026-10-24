<x-filament-panels::page>
    <div class="space-y-6">
        @php
            $video = $this->selectedVideo;
            $allVideos = $this->videoList;
            $platformsCount = count($this->availablePlatforms);
        @endphp

        {{-- Üst Kontrol & Video Navigasyon Paneli (Hero Bar) --}}
        <div class="ai-studio-hero p-5 md:p-6 transition-all duration-300">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                {{-- Sol: Video Seçimi ve Navigasyon --}}
                <div class="flex-1 max-w-2xl">
                    <div class="flex items-center justify-between mb-2">
                        <label class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                            <x-heroicon-m-film class="w-4 h-4 text-primary-500" />
                            <span>İçerik Üretilecek Video</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
                                {{ $allVideos->count() }} Video Mevcut
                            </span>
                        </label>

                        {{-- Önceki / Sonraki Hızlı Butonlar --}}
                        <div class="flex items-center gap-1">
                            <button 
                                type="button"
                                wire:click="previousVideo"
                                @disabled(! $this->hasPreviousVideo)
                                class="inline-flex items-center justify-center p-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition text-xs"
                                title="Önceki Video"
                            >
                                <x-heroicon-m-chevron-left class="w-4 h-4" />
                            </button>
                            <button 
                                type="button"
                                wire:click="nextVideo"
                                @disabled(! $this->hasNextVideo)
                                class="inline-flex items-center justify-center p-1.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed transition text-xs"
                                title="Sonraki Video"
                            >
                                <x-heroicon-m-chevron-right class="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    <div class="relative">
                        <select 
                            wire:model.live="selectedVideoId" 
                            class="w-full text-sm font-medium rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900/90 text-gray-900 dark:text-gray-100 shadow-sm focus:border-primary-500 focus:ring-primary-500 py-2.5 pl-3 pr-10"
                        >
                            <option value="">-- Bir Video Seçin --</option>
                            @foreach($allVideos as $v)
                                <option value="{{ $v->id }}">
                                    {{ $v->title }} ({{ $v->formatted_duration }} • {{ $v->aspect_ratio }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if($video)
                        {{-- Video Bilgi Rozetleri & Mini Thumbnail --}}
                        <div class="mt-4 flex flex-wrap items-center gap-2.5 text-xs">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800/80 text-gray-700 dark:text-gray-300 font-medium border border-gray-200/60 dark:border-gray-700/60">
                                <x-heroicon-m-device-phone-mobile class="w-3.5 h-3.5 text-primary-500" />
                                <span>Format: <strong>{{ $video->aspect_ratio }}</strong></span>
                            </span>

                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800/80 text-gray-700 dark:text-gray-300 font-medium border border-gray-200/60 dark:border-gray-700/60">
                                <x-heroicon-m-clock class="w-3.5 h-3.5 text-sky-500" />
                                <span>Süre: <strong>{{ $video->formatted_duration }}</strong></span>
                            </span>

                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-100 dark:bg-gray-800/80 text-gray-700 dark:text-gray-300 font-medium border border-gray-200/60 dark:border-gray-700/60">
                                <x-heroicon-m-tag class="w-3.5 h-3.5 text-amber-500" />
                                <span>Marka: <strong>{{ $video->brand?->name ?? 'Patenli Ayakkabılar' }}</strong></span>
                            </span>

                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 font-medium border border-emerald-200/60 dark:border-emerald-800/60">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span>{{ $video->status->label() }}</span>
                            </span>

                            @php
                                $readyDrafts = count(array_filter($platformContents, fn($p) => !empty($p['caption'])));
                            @endphp
                            @if($readyDrafts > 0)
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 font-semibold border border-indigo-200/60 dark:border-indigo-800/60">
                                    <x-heroicon-m-sparkles class="w-3.5 h-3.5 text-indigo-500" />
                                    <span>{{ $readyDrafts }}/{{ $platformsCount }} Platform Hazır</span>
                                </span>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Sağ: AI Üretim Aksiyon Kartı --}}
                <div class="flex flex-col sm:flex-row lg:flex-col items-start lg:items-end justify-center gap-2.5 shrink-0">
                    <button 
                        type="button"
                        wire:click="generateAiContent"
                        wire:loading.attr="disabled"
                        class="ai-studio-btn-magic inline-flex items-center justify-center gap-2.5 px-5 py-3 rounded-xl font-bold text-sm tracking-wide shadow-md hover:shadow-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer w-full sm:w-auto"
                    >
                        <x-heroicon-m-sparkles class="w-5 h-5 text-amber-300 animate-spin-slow" />
                        <span wire:loading.remove>{{ $platformsCount }} Platform İçin Akıllı İçerik Üret</span>
                        <span wire:loading class="inline-flex items-center gap-2">
                            <svg class="animate-spin -ml-1 mr-1 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Yapay Zekâ Analiz Ediyor...
                        </span>
                    </button>

                    <div class="flex items-center gap-3 text-[11px] text-gray-500 dark:text-gray-400">
                        <button 
                            type="button"
                            wire:click="$set('showApiKeyModal', true)"
                            class="inline-flex items-center gap-1.5 font-medium hover:text-primary-500 transition cursor-pointer"
                            title="OpenAI API Anahtarı ve Model Ayarları"
                        >
                            @if($this->isOpenAiConfigured)
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                <span class="text-emerald-600 dark:text-emerald-400 font-bold">OpenAI Aktif ({{ $this->maskedApiKey }})</span>
                            @else
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                <span class="text-amber-600 dark:text-amber-400 font-bold">API Anahtarı Tanımla</span>
                            @endif
                            <x-heroicon-m-cog-6-tooth class="w-3.5 h-3.5 text-gray-400 hover:text-gray-600" />
                        </button>
                        <span>•</span>
                        <button 
                            type="button" 
                            wire:click="saveAllPlatformContents" 
                            class="hover:text-primary-500 hover:underline font-semibold cursor-pointer text-gray-600 dark:text-gray-300"
                        >
                            Tümünü Kaydet
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @if($this->selectedVideoId)
            {{-- Ana İçerik Paneli: Sekmeler ve İki Kolonlu Stüdyo --}}
            <div class="ai-studio-panel bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden">
                
                {{-- Kanal Filtresi ve Platform Yönetim Çubuğu --}}
                <div class="border-b border-gray-200 dark:border-gray-800 px-5 py-3 bg-gray-50/80 dark:bg-gray-950/70 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider text-[11px]">Yayın Kanalları:</span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-primary-50 dark:bg-primary-950/50 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-800">
                            {{ $platformsCount }} Platform
                        </span>
                    </div>

                    <div class="flex items-center gap-5">
                        <label class="inline-flex items-center gap-2 cursor-pointer select-none text-gray-700 dark:text-gray-300 text-xs font-medium">
                            <input 
                                type="checkbox" 
                                wire:model.live="onlyConnected"
                                class="rounded border-gray-300 dark:border-gray-700 text-primary-600 focus:ring-primary-500 w-4 h-4 cursor-pointer"
                            />
                            <span>Yalnızca Bağlı Hesapları Göster</span>
                        </label>

                        <a 
                            href="{{ route('filament.admin.pages.social-channels') }}" 
                            class="text-xs text-primary-600 dark:text-primary-400 hover:text-primary-700 dark:hover:text-primary-300 inline-flex items-center gap-1.5 font-bold transition"
                        >
                            <span>Hesapları Yönet</span>
                            <x-heroicon-m-arrow-top-right-on-square class="w-3.5 h-3.5" />
                        </a>
                    </div>
                </div>

                {{-- Platform Sekmeleri (Özel Marka Logoları & Hesap İsimleri ile) --}}
                <div class="border-b border-gray-200 dark:border-gray-800 flex overflow-x-auto bg-gray-50/40 dark:bg-gray-950/40 p-1.5 gap-1.5 scrollbar-thin">
                    @foreach($this->availablePlatforms as $key => $tabInfo)
                        @php
                            $isActive = ($activeTab === $key);
                            $hasContent = !empty($platformContents[$key]['caption']);
                            $isConnected = !empty($tabInfo['is_connected']);
                        @endphp
                        <button 
                            type="button"
                            wire:click="$set('activeTab', '{{ $key }}')"
                            class="ai-platform-tab px-3.5 py-2.5 rounded-xl text-xs font-semibold flex items-center gap-2.5 whitespace-nowrap transition cursor-pointer {{ $isActive ? 'is-active text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200 hover:bg-gray-100/70 dark:hover:bg-gray-800/50' }}"
                        >
                            {{-- Platform Resmi Marka Simgesi --}}
                            @if($key === 'instagram')
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
                                    <defs>
                                        <linearGradient id="ig-grad-{{ $key }}" x1="0%" y1="100%" x2="100%" y2="0%">
                                            <stop offset="0%" stop-color="#FFD600"/>
                                            <stop offset="25%" stop-color="#FF7A00"/>
                                            <stop offset="50%" stop-color="#FF0069"/>
                                            <stop offset="75%" stop-color="#D300C5"/>
                                            <stop offset="100%" stop-color="#7638FA"/>
                                        </linearGradient>
                                    </defs>
                                    <rect x="2" y="2" width="20" height="20" rx="6" stroke="url(#ig-grad-{{ $key }})" stroke-width="2"/>
                                    <circle cx="12" cy="12" r="4.5" stroke="url(#ig-grad-{{ $key }})" stroke-width="2"/>
                                    <circle cx="17.5" cy="6.5" r="1.2" fill="url(#ig-grad-{{ $key }})"/>
                                </svg>
                            @elseif($key === 'youtube')
                                <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                    <path fill="#FF0000" d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                </svg>
                            @elseif($key === 'facebook')
                                <svg class="w-4 h-4 shrink-0 text-[#1877F2]" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                            @elseif($key === 'x')
                                <svg class="w-4 h-4 shrink-0 fill-current" viewBox="0 0 24 24">
                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                </svg>
                            @elseif($key === 'tiktok')
                                <svg class="w-4 h-4 shrink-0 fill-current text-gray-900 dark:text-white" viewBox="0 0 24 24">
                                    <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-1.01-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.24 1.07-.14 1.61.24 1.64 1.82 2.89 3.5 2.76 1.44-.04 2.7-1.03 3.06-2.42.14-.54.2-1.1.19-1.66.02-5.28.01-10.56.01-15.84z"/>
                                </svg>
                            @elseif($key === 'linkedin')
                                <svg class="w-4 h-4 shrink-0 text-[#0A66C2]" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451c.979 0 1.778-.773 1.778-1.729V1.73C24 .774 23.205 0 22.222 0h.003z"/>
                                </svg>
                            @elseif($key === 'pinterest')
                                <svg class="w-4 h-4 shrink-0 text-[#E60023]" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 0 1 .083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/>
                                </svg>
                            @else
                                <x-heroicon-m-globe-alt class="w-4 h-4 shrink-0 text-gray-500" />
                            @endif

                            <span>{{ $tabInfo['name'] }}</span>

                            @if($isConnected)
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                                    <span class="w-1 h-1 rounded-full bg-emerald-500"></span>
                                    <span>{{ $tabInfo['username'] ? '@' . $tabInfo['username'] : 'Bağlı' }}</span>
                                </span>
                            @else
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                    Bağlantı Yok
                                </span>
                            @endif

                            @if($hasContent)
                                <span class="w-1.5 h-1.5 rounded-full bg-primary-500" title="İçerik Hazır"></span>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- İki Kolonlu Stüdyo Çalışma Alanı --}}
                <div class="p-5 md:p-6">
                    @php
                        $cfg = $this->getPlatformConfig($activeTab);
                        $cur = $platformContents[$activeTab] ?? null;
                        $captionText = $cur['caption'] ?? '';
                        $charCount = mb_strlen($captionText);
                        $maxChar = $cfg['caption_max'] ?? 2200;
                        $charPercent = min(100, round(($charCount / max(1, $maxChar)) * 100));
                        $isOverLimit = $charCount > $maxChar;
                        $activeAccountName = $this->availablePlatforms[$activeTab]['account_name'] ?? 'Patenli Ayakkabılar';
                        $activeUsername = $this->availablePlatforms[$activeTab]['username'] ?? 'patenliayakkabilar';
                    @endphp

                    {{-- Kanal Bağlantı Uyarısı --}}
                    @if(isset($this->availablePlatforms[$activeTab]) && empty($this->availablePlatforms[$activeTab]['is_connected']))
                        <div class="mb-5 p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-900/60 text-amber-800 dark:text-amber-300 text-xs flex items-center justify-between gap-3 shadow-xs">
                            <div class="flex items-center gap-2.5">
                                <x-heroicon-m-exclamation-triangle class="w-4 h-4 text-amber-500 shrink-0" />
                                <span>Bu platform (<strong>{{ $this->availablePlatforms[$activeTab]['name'] }}</strong>) için henüz aktif bir sosyal medya hesabı bağlanmamış. Taslak metin hazırlayabilir ve ardından kanalı bağlayabilirsiniz.</span>
                            </div>
                            <a href="{{ route('filament.admin.pages.social-channels', ['platform' => $activeTab]) }}" class="font-bold underline shrink-0 hover:text-amber-950 dark:hover:text-amber-100 flex items-center gap-1">
                                <span>Kanalı Bağla</span>
                                <x-heroicon-m-arrow-right class="w-3.5 h-3.5" />
                            </a>
                        </div>
                    @endif

                    <div 
                        x-data="{
                            copiedCaption: false,
                            copiedHook: false,
                            copiedTags: false,
                            previewTab: 'live',
                            copyToClipboard(text, type) {
                                navigator.clipboard.writeText(text);
                                if (type === 'caption') { this.copiedCaption = true; setTimeout(() => this.copiedCaption = false, 2000); }
                                if (type === 'hook') { this.copiedHook = true; setTimeout(() => this.copiedHook = false, 2000); }
                                if (type === 'tags') { this.copiedTags = true; setTimeout(() => this.copiedTags = false, 2000); }
                            }
                        }" 
                        class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start"
                    >
                        {{-- SOL KOLON: İçerik Düzenleme ve Optimizasyon Formu --}}
                        <div class="lg:col-span-7 space-y-5">
                            
                            {{-- Editör Başlığı ve Hızlı Araç Çubuğu --}}
                            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ $this->availablePlatforms[$activeTab]['name'] }} İçerik Editörü</span>
                                    @if(!empty($cur['ai_model']))
                                        <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-500">
                                            {{ str_contains($cur['ai_model'], 'test-mode') ? 'Şablon Motoru' : $cur['ai_model'] }}
                                        </span>
                                    @endif
                                </div>

                                {{-- Hızlı Panoya Kopyalama Araçları --}}
                                <div class="flex items-center gap-2">
                                    <button 
                                        type="button"
                                        x-on:click="copyToClipboard(@js($captionText . ' ' . ($cur['hashtags'] ?? '')), 'caption')"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition cursor-pointer"
                                        title="Açıklama ve etiketleri panoya kopyala"
                                    >
                                        <x-heroicon-m-clipboard-document class="w-3.5 h-3.5 text-primary-500" />
                                        <span x-show="!copiedCaption">Metni Kopyala</span>
                                        <span x-show="copiedCaption" class="text-emerald-500 font-bold" style="display: none;">Kopyalandı!</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Test Modu Bilgilendirme ve Hızlı Yeniden Üretim Kartı --}}
                            @if(!empty($cur['ai_model']) && str_contains($cur['ai_model'], 'test-mode'))
                                <div class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/80 dark:border-amber-900/60 text-amber-900 dark:text-amber-200 text-xs flex flex-wrap items-center justify-between gap-3 shadow-xs">
                                    <div class="flex items-center gap-2.5">
                                        <x-heroicon-m-beaker class="w-5 h-5 text-amber-500 shrink-0" />
                                        <div>
                                            <span class="font-bold">Önceki Şablon Çıktısı:</span>
                                            <span>Bu metin daha önce test motoruyla kaydedilmiştir. @if($this->isOpenAiConfigured) Tanımlı OpenAI anahtarınızla GPT-4o üzerinden taze içerik üretmek için butona tıklayın: @else OpenAI API anahtarınızı girerek yapay zekâ üretimini başlatabilirsiniz. @endif</span>
                                        </div>
                                    </div>
                                    @if($this->isOpenAiConfigured)
                                        <button 
                                            type="button" 
                                            wire:click="generateAiContent" 
                                            wire:loading.attr="disabled"
                                            class="px-3.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs shadow-xs cursor-pointer inline-flex items-center gap-1.5 transition"
                                        >
                                            <x-heroicon-m-sparkles class="w-4 h-4" />
                                            <span>Şimdi GPT-4o ile Üret</span>
                                        </button>
                                    @else
                                        <button 
                                            type="button" 
                                            wire:click="$set('showApiKeyModal', true)" 
                                            class="px-3.5 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs shadow-xs cursor-pointer inline-flex items-center gap-1.5 transition"
                                        >
                                            <x-heroicon-m-key class="w-4 h-4" />
                                            <span>API Anahtarını Tanımla</span>
                                        </button>
                                    @endif
                                </div>
                            @endif

                            {{-- Platform Başlığı (YouTube, Pinterest, Facebook için) --}}
                            @if(!empty($cfg['has_title']))
                                <div class="p-4 rounded-xl bg-gray-50/60 dark:bg-gray-800/40 border border-gray-200/70 dark:border-gray-800 space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">
                                            {{ $cfg['title_label'] }}
                                            @if(!empty($cfg['title_required']))
                                                <span class="text-rose-500">*</span>
                                            @endif
                                        </label>
                                        @if(!empty($cfg['title_max']))
                                            <span class="text-[11px] font-mono text-gray-400">
                                                {{ mb_strlen($cur['title'] ?? '') }} / {{ $cfg['title_max'] }}
                                            </span>
                                        @endif
                                    </div>
                                    <input 
                                        type="text" 
                                        wire:model.live.debounce.300ms="platformContents.{{ $activeTab }}.title" 
                                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-xs focus:border-primary-500 focus:ring-primary-500"
                                        placeholder="{{ $cfg['title_placeholder'] }}"
                                        maxlength="{{ $cfg['title_max'] ?? 255 }}"
                                    />
                                </div>
                            @endif

                            {{-- İlk 3 Saniye Kancası (Hook) - Özel Vurgulu Kart --}}
                            @if(!empty($cfg['has_hook']))
                                <div class="p-4 rounded-xl bg-gradient-to-r from-amber-500/10 via-orange-500/5 to-transparent border border-amber-500/25 dark:border-amber-500/20 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            <span class="flex h-2 w-2 rounded-full bg-amber-500"></span>
                                            <label class="block text-xs font-bold text-amber-900 dark:text-amber-200">
                                                {{ $cfg['hook_label'] }}
                                            </label>
                                        </div>
                                        <button 
                                            type="button"
                                            x-on:click="copyToClipboard(@js($cur['hook_sentence'] ?? ''), 'hook')"
                                            class="text-[11px] font-semibold text-amber-700 dark:text-amber-400 hover:underline inline-flex items-center gap-1 cursor-pointer"
                                        >
                                            <span x-show="!copiedHook">Kancayı Kopyala</span>
                                            <span x-show="copiedHook" class="text-emerald-500 font-bold" style="display: none;">✓ Kopyalandı</span>
                                        </button>
                                    </div>
                                    <input 
                                        type="text" 
                                        wire:model.live.debounce.300ms="platformContents.{{ $activeTab }}.hook_sentence" 
                                        class="w-full text-sm font-semibold rounded-lg border-amber-300/80 dark:border-amber-900/80 dark:bg-gray-900/90 text-gray-900 dark:text-gray-100 shadow-xs focus:border-amber-500 focus:ring-amber-500"
                                        placeholder="{{ $cfg['hook_placeholder'] }}"
                                    />
                                    <p class="text-[11px] text-amber-700/80 dark:text-amber-400/80 flex items-center gap-1">
                                        <x-heroicon-m-light-bulb class="w-3.5 h-3.5 shrink-0" />
                                        <span>İzleyicinin videoda kalmasını sağlayan, ekranda veya ilk saniyelerde vurgulanacak açılış cümlesi.</span>
                                    </p>
                                </div>
                            @endif

                            {{-- Açıklama / Gönderi Metni (Caption) --}}
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">
                                        {{ $cfg['caption_label'] }}
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-mono {{ $isOverLimit ? 'text-rose-500 font-bold' : ($charPercent > 85 ? 'text-amber-500 font-semibold' : 'text-gray-400') }}">
                                            {{ $charCount }} / {{ $maxChar }} karakter
                                        </span>
                                    </div>
                                </div>

                                {{-- Karakter Limit İlerleme Çubuğu --}}
                                <div class="w-full h-1 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                    <div 
                                        class="h-full transition-all duration-300 rounded-full {{ $isOverLimit ? 'bg-rose-500' : ($charPercent > 85 ? 'bg-amber-500' : 'bg-primary-500') }}"
                                        style="width: {{ $charPercent }}%"
                                    ></div>
                                </div>

                                <textarea 
                                    rows="{{ $activeTab === 'x' ? 4 : 6 }}" 
                                    wire:model.live.debounce.300ms="platformContents.{{ $activeTab }}.caption"
                                    class="w-full text-sm leading-relaxed rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-xs focus:border-primary-500 focus:ring-primary-500 font-normal p-3.5"
                                    placeholder="{{ $cfg['caption_placeholder'] }}"
                                ></textarea>
                            </div>

                            {{-- Hashtag'ler ve Call to Action Alanı --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">
                                            {{ $cfg['hashtags_label'] }}
                                        </label>
                                        <button 
                                            type="button"
                                            x-on:click="copyToClipboard(@js($cur['hashtags'] ?? ''), 'tags')"
                                            class="text-[10px] text-primary-600 dark:text-primary-400 hover:underline cursor-pointer font-semibold"
                                        >
                                            <span x-show="!copiedTags">Etiketleri Kopyala</span>
                                            <span x-show="copiedTags" class="text-emerald-500 font-bold" style="display: none;">✓ Kopyalandı</span>
                                        </button>
                                    </div>
                                    <input 
                                        type="text" 
                                        wire:model.live.debounce.300ms="platformContents.{{ $activeTab }}.hashtags" 
                                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-xs focus:border-primary-500 focus:ring-primary-500"
                                        placeholder="{{ $cfg['hashtags_placeholder'] }}"
                                    />
                                    
                                    {{-- Etiket Rozetleri Önizlemesi --}}
                                    @php
                                        $tagsRaw = $cur['hashtags'] ?? '';
                                        $parsedTags = array_filter(array_map('trim', explode(' ', str_replace(['#', ','], ' ', $tagsRaw))));
                                    @endphp
                                    @if(!empty($parsedTags))
                                        <div class="flex flex-wrap gap-1.5 pt-1">
                                            @foreach(array_slice($parsedTags, 0, 6) as $tag)
                                                <span class="ai-hashtag-chip">#{{ $tag }}</span>
                                            @endforeach
                                            @if(count($parsedTags) > 6)
                                                <span class="text-[10px] text-gray-400 self-center">+{{ count($parsedTags) - 6 }} daha</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>

                                <div class="space-y-1.5">
                                    <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">
                                        {{ $cfg['cta_label'] }}
                                    </label>
                                    <input 
                                        type="text" 
                                        wire:model.live.debounce.300ms="platformContents.{{ $activeTab }}.call_to_action" 
                                        class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-xs focus:border-primary-500 focus:ring-primary-500"
                                        placeholder="Hemen inceleyin veya sipariş verin..."
                                    />
                                    <p class="text-[10px] text-gray-400">İzleyiciyi profile, yoruma veya web sitesine çeken çağrı.</p>
                                </div>
                            </div>

                            {{-- Hedef URL ve Platform Davranış Rehberi --}}
                            <div class="space-y-2">
                                <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">
                                    {{ $cfg['url_label'] }}
                                </label>
                                <div class="flex items-center gap-2">
                                    <input 
                                        type="url" 
                                        wire:model.live.debounce.300ms="platformContents.{{ $activeTab }}.suggested_product_url" 
                                        class="flex-1 text-xs font-mono rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-xs focus:border-primary-500 focus:ring-primary-500"
                                        placeholder="https://patenliayakkabilar.com"
                                    />
                                    @if(!empty($cur['suggested_product_url']))
                                        <a 
                                            href="{{ $cur['suggested_product_url'] }}" 
                                            target="_blank" 
                                            class="inline-flex items-center justify-center p-2 rounded-lg bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 text-gray-600 dark:text-gray-300 transition"
                                            title="Linki Tarayıcıda Aç"
                                        >
                                            <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4" />
                                        </a>
                                    @endif
                                </div>

                                @if(!empty($cfg['url_note']))
                                    <div class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-950/60 border border-gray-200/80 dark:border-gray-800/80 text-[11px] text-gray-600 dark:text-gray-400 flex items-start gap-2">
                                        <x-heroicon-m-information-circle class="w-4 h-4 text-primary-500 shrink-0 mt-0.5" />
                                        <span>{{ $cfg['url_note'] }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Alt Onay ve Kaydetme Çubuğu --}}
                            <div class="pt-4 border-t border-gray-100 dark:border-gray-800 flex flex-wrap items-center justify-between gap-4">
                                <div class="flex items-center gap-2">
                                    <label class="text-xs font-bold text-gray-700 dark:text-gray-300">Onay Durumu:</label>
                                    <select 
                                        wire:model="platformContents.{{ $activeTab }}.status" 
                                        class="text-xs font-semibold rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-xs"
                                    >
                                        <option value="draft">Taslak</option>
                                        <option value="approved">Onaylandı (Yayına Hazır)</option>
                                        <option value="rejected">Reddedildi</option>
                                    </select>
                                </div>

                                <div class="flex items-center gap-2.5">
                                    <button 
                                        type="button"
                                        wire:click="savePlatformContent('{{ $activeTab }}')"
                                        class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-bold rounded-xl text-white bg-emerald-600 hover:bg-emerald-500 shadow-sm hover:shadow transition cursor-pointer"
                                    >
                                        <x-heroicon-m-check class="w-4 h-4" />
                                        <span>{{ ucfirst($activeTab) }} İçeriğini Kaydet</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- SAĞ KOLON: Canlı Sosyal Medya Simülatörü (Live Social Feed Preview) --}}
                        <div class="lg:col-span-5 space-y-4">
                            <div class="flex items-center justify-between px-1">
                                <div class="flex items-center gap-2">
                                    <x-heroicon-m-device-phone-mobile class="w-4 h-4 text-primary-500" />
                                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200">Canlı Sosyal Önizleme</span>
                                </div>
                                <span class="text-[10px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded bg-primary-50 dark:bg-primary-950/60 text-primary-600 dark:text-primary-400 border border-primary-200/50 dark:border-primary-800/50">
                                    {{ $this->availablePlatforms[$activeTab]['name'] }}
                                </span>
                            </div>

                            @if(in_array($activeTab, ['instagram', 'tiktok', 'youtube']))
                                {{-- DİKEY VİDEO PLATFORMLARI (Instagram Reels / TikTok / YouTube Shorts) - Gerçekçi Telefon Bezel'i --}}
                                <div class="ai-phone-frame">
                                    {{-- Dinamik Ada / Hoparlör Notchu --}}
                                    <div class="ai-phone-island">
                                        <div class="ai-phone-island-lens"></div>
                                    </div>

                                    <div class="relative w-full aspect-[9/16] bg-black overflow-hidden flex flex-col justify-between">
                                        {{-- Video Oynatıcı veya Arka Plan Görseli --}}
                                        @if($video && $video->url)
                                            <video 
                                                src="{{ $video->url }}" 
                                                poster="{{ $video->thumbnail_url }}"
                                                muted 
                                                playsinline 
                                                loop 
                                                autoplay
                                                class="absolute inset-0 w-full h-full object-cover pointer-events-none opacity-90"
                                            ></video>
                                        @elseif($video && $video->thumbnail_url)
                                            <img 
                                                src="{{ $video->thumbnail_url }}" 
                                                alt="{{ $video->title }}" 
                                                class="absolute inset-0 w-full h-full object-cover opacity-85"
                                            />
                                        @else
                                            <div class="absolute inset-0 bg-gradient-to-b from-gray-900 via-gray-950 to-black flex items-center justify-center">
                                                <x-heroicon-o-film class="w-12 h-12 text-gray-700" />
                                            </div>
                                        @endif

                                        {{-- Üst Karartma Katmanı & Header --}}
                                        <div class="relative z-10 p-4 pt-8 bg-gradient-to-b from-black/80 via-black/30 to-transparent flex items-center justify-between text-white text-xs">
                                            <span class="font-bold tracking-tight">
                                                @if($activeTab === 'instagram') Reels @elseif($activeTab === 'tiktok') Sizin İçin @else Shorts @endif
                                            </span>
                                            <x-heroicon-m-camera class="w-4 h-4 opacity-80" />
                                        </div>

                                        {{-- Ortada Vurgulanan Kanca (Hook Sticker) --}}
                                        @if(!empty($cur['hook_sentence']))
                                            <div class="relative z-10 px-5 text-center">
                                                <div class="ai-hook-sticker inline-block px-3.5 py-2 text-xs leading-snug font-extrabold max-w-[90%] shadow-lg">
                                                    {{ $cur['hook_sentence'] }}
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Sağ Aksiyon Butonları (Like, Comment, Share) --}}
                                        <div class="relative z-10 self-end pr-3 pb-24 flex flex-col items-center gap-3 text-white text-[10px]">
                                            <div class="flex flex-col items-center gap-0.5">
                                                <div class="w-9 h-9 rounded-full bg-white/15 backdrop-blur-md flex items-center justify-center shadow-md">
                                                    <x-heroicon-s-heart class="w-5 h-5 text-rose-500" />
                                                </div>
                                                <span class="font-bold">14.2K</span>
                                            </div>

                                            <div class="flex flex-col items-center gap-0.5">
                                                <div class="w-9 h-9 rounded-full bg-white/15 backdrop-blur-md flex items-center justify-center shadow-md">
                                                    <x-heroicon-s-chat-bubble-oval-left-ellipsis class="w-5 h-5" />
                                                </div>
                                                <span class="font-bold">342</span>
                                            </div>

                                            <div class="flex flex-col items-center gap-0.5">
                                                <div class="w-9 h-9 rounded-full bg-white/15 backdrop-blur-md flex items-center justify-center shadow-md">
                                                    <x-heroicon-s-paper-airplane class="w-4 h-4 -rotate-45" />
                                                </div>
                                                <span class="font-bold">890</span>
                                            </div>

                                            <div class="w-8 h-8 rounded-full bg-black/60 border border-white/40 flex items-center justify-center mt-1 animate-spin-slow">
                                                <x-heroicon-m-musical-note class="w-4 h-4 text-white" />
                                            </div>
                                        </div>

                                        {{-- Alt Metin, Kullanıcı ve Başlık Katmanı --}}
                                        <div class="relative z-10 p-4 pt-10 bg-gradient-to-t from-black via-black/85 to-transparent text-white text-xs space-y-2">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-gradient-to-tr from-amber-400 to-rose-600 flex items-center justify-center text-[10px] font-bold text-white shadow-xs">
                                                    {{ strtoupper(substr($activeUsername, 0, 1)) }}
                                                </div>
                                                <span class="font-bold text-xs">{{ '@' . $activeUsername }}</span>
                                                <button type="button" class="px-2 py-0.5 rounded text-[10px] font-bold bg-white/20 hover:bg-white/30 backdrop-blur-sm">Takip Et</button>
                                            </div>

                                            {{-- Dinamik Başlık / Açıklama --}}
                                            @if(!empty($cur['title']))
                                                <p class="font-extrabold text-xs line-clamp-1 text-white">{{ $cur['title'] }}</p>
                                            @endif

                                            <p class="text-[11px] leading-relaxed text-gray-200 line-clamp-3">
                                                {{ $captionText ?: 'İçerik açıklaması burada canlı olarak önizlenecektir...' }}
                                            </p>

                                            {{-- Hashtag'ler --}}
                                            @if(!empty($cur['hashtags']))
                                                <p class="text-[11px] font-medium text-sky-300 line-clamp-1">
                                                    {{ $cur['hashtags'] }}
                                                </p>
                                            @endif

                                            {{-- Müzik / Ses Çubuğu --}}
                                            <div class="flex items-center gap-2 text-[10px] text-gray-300 pt-1">
                                                <x-heroicon-m-musical-note class="w-3 h-3 text-emerald-400 shrink-0" />
                                                <span class="truncate">Orijinal Ses - {{ $activeAccountName }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @elseif($activeTab === 'x')
                                {{-- X (TWITTER) ÖZEL KART GÖRÜNÜMÜ --}}
                                <div class="ai-x-card p-4 text-xs space-y-3 shadow-md">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-xs shadow-xs">
                                                {{ strtoupper(substr($activeUsername, 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-1 font-bold text-gray-900 dark:text-gray-100">
                                                    <span>{{ $activeAccountName }}</span>
                                                    <x-heroicon-s-check-badge class="w-4 h-4 text-sky-500 inline" />
                                                </div>
                                                <span class="text-gray-500 text-[11px]">{{ '@' . $activeUsername }} · Şimdi</span>
                                            </div>
                                        </div>
                                        <svg class="w-4 h-4 fill-current text-gray-700 dark:text-gray-300" viewBox="0 0 24 24">
                                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                        </svg>
                                    </div>

                                    {{-- Tweet Metni --}}
                                    <p class="text-sm leading-relaxed text-gray-900 dark:text-gray-100 whitespace-pre-line">
                                        {{ $captionText ?: '280 karakterlik tweet metniniz burada anlık olarak önizlenecektir...' }}
                                    </p>

                                    @if(!empty($cur['hashtags']))
                                        <p class="text-sm font-semibold text-sky-500">
                                            {{ $cur['hashtags'] }}
                                        </p>
                                    @endif

                                    {{-- Tweet Medya Kutusu --}}
                                    @if($video)
                                        <div class="relative rounded-2xl overflow-hidden border border-gray-200 dark:border-gray-800 bg-black aspect-video flex items-center justify-center">
                                            @if($video->thumbnail_url)
                                                <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" class="w-full h-full object-cover opacity-80" />
                                            @endif
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <div class="w-11 h-11 rounded-full bg-primary-600/90 text-white flex items-center justify-center shadow-lg">
                                                    <x-heroicon-s-play class="w-5 h-5 ml-0.5" />
                                                </div>
                                            </div>
                                            <span class="absolute bottom-2 right-2 px-1.5 py-0.5 rounded bg-black/70 text-white text-[10px] font-mono">
                                                {{ $video->formatted_duration }}
                                            </span>
                                        </div>
                                    @endif

                                    {{-- X Etkileşim Butonları --}}
                                    <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-800/80 text-gray-500 text-xs">
                                        <span class="flex items-center gap-1.5 hover:text-sky-500 cursor-pointer"><x-heroicon-m-chat-bubble-oval-left class="w-4 h-4" /> 18</span>
                                        <span class="flex items-center gap-1.5 hover:text-emerald-500 cursor-pointer"><x-heroicon-m-arrow-path-rounded-square class="w-4 h-4" /> 42</span>
                                        <span class="flex items-center gap-1.5 hover:text-rose-500 cursor-pointer"><x-heroicon-m-heart class="w-4 h-4" /> 312</span>
                                        <span class="flex items-center gap-1.5 hover:text-sky-500 cursor-pointer"><x-heroicon-m-chart-bar class="w-4 h-4" /> 4.5K</span>
                                        <x-heroicon-m-bookmark class="w-4 h-4 hover:text-sky-500 cursor-pointer" />
                                    </div>
                                </div>
                            @else
                                {{-- FACEBOOK / LINKEDIN / PINTEREST KART GÖRÜNÜMÜ --}}
                                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 p-4 text-xs space-y-3 shadow-md">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-700 flex items-center justify-center text-white font-bold text-xs shadow-xs">
                                                {{ strtoupper(substr($activeAccountName, 0, 1)) }}
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-gray-900 dark:text-gray-100">{{ $activeAccountName }}</h4>
                                                <div class="flex items-center gap-1 text-[11px] text-gray-500">
                                                    <span>Şimdi</span>
                                                    <span>•</span>
                                                    <x-heroicon-m-globe-americas class="w-3.5 h-3.5" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    @if(!empty($cur['title']))
                                        <h3 class="font-extrabold text-sm text-gray-900 dark:text-gray-100">{{ $cur['title'] }}</h3>
                                    @endif

                                    <p class="text-xs leading-relaxed text-gray-800 dark:text-gray-200 whitespace-pre-line line-clamp-6">
                                        {{ $captionText ?: 'Gönderi metni burada canlı olarak önizlenecektir...' }}
                                    </p>

                                    @if(!empty($cur['hashtags']))
                                        <p class="text-xs font-semibold text-primary-600 dark:text-primary-400">
                                            {{ $cur['hashtags'] }}
                                        </p>
                                    @endif

                                    @if($video)
                                        <div class="relative rounded-xl overflow-hidden border border-gray-200 dark:border-gray-800 bg-black aspect-video flex items-center justify-center">
                                            @if($video->thumbnail_url)
                                                <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" class="w-full h-full object-cover opacity-80" />
                                            @endif
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <div class="w-12 h-12 rounded-full bg-white/20 backdrop-blur-md text-white flex items-center justify-center shadow-lg border border-white/30">
                                                    <x-heroicon-s-play class="w-6 h-6 ml-0.5" />
                                                </div>
                                            </div>
                                        </div>
                                    @endif

                                    @if(!empty($cur['suggested_product_url']))
                                        <div class="p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 flex items-center justify-between text-[11px]">
                                            <span class="truncate font-mono text-gray-600 dark:text-gray-300">{{ $cur['suggested_product_url'] }}</span>
                                            <span class="font-bold text-primary-600 dark:text-primary-400 shrink-0">İncele →</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- Video Seçilmedi Durumu --}}
            <x-filament::section>
                <div class="text-center py-12 text-gray-500 dark:text-gray-400 text-sm space-y-3">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-primary-50 dark:bg-primary-950/40 border border-primary-200 dark:border-primary-800 flex items-center justify-center text-primary-500">
                        <x-heroicon-o-sparkles class="w-8 h-8" />
                    </div>
                    <div class="max-w-md mx-auto space-y-1">
                        <h3 class="font-bold text-base text-gray-900 dark:text-gray-100">AI İçerik Stüdyosu'na Hoş Geldiniz</h3>
                        <p class="text-xs text-gray-500">Metin ve platform optimizasyonu üretmek için lütfen yukarıdaki menüden bir video seçin.</p>
                    </div>
                </div>
            </x-filament::section>
        @endif

        {{-- OpenAI API Anahtarı ve Model Yönetim Modalı --}}
        @if($showApiKeyModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl max-w-md w-full shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                    <div class="p-5 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between bg-gray-50/50 dark:bg-gray-950/50">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-primary-50 dark:bg-primary-950/50 text-primary-600 dark:text-primary-400 flex items-center justify-center font-bold">
                                <x-heroicon-m-key class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="font-bold text-sm text-gray-900 dark:text-gray-100">OpenAI API Ayarları</h3>
                                <p class="text-[11px] text-gray-500">GPT-4o ve yapay zekâ entegrasyonu</p>
                            </div>
                        </div>
                        <button 
                            type="button" 
                            wire:click="$set('showApiKeyModal', false)" 
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded-lg"
                        >
                            <x-heroicon-m-x-mark class="w-5 h-5" />
                        </button>
                    </div>

                    <div class="p-5 space-y-4">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300">OpenAI API Anahtarı</label>
                                @if($this->isOpenAiConfigured)
                                    <span class="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 font-semibold">
                                        Mevcut: {{ $this->maskedApiKey }}
                                    </span>
                                @endif
                            </div>
                            <input 
                                type="password" 
                                wire:model="openAiKeyInput" 
                                class="w-full text-xs font-mono rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-950 text-gray-900 dark:text-gray-100 shadow-xs focus:border-primary-500 focus:ring-primary-500"
                                placeholder="{{ $this->isOpenAiConfigured ? 'Yeni bir anahtar girmek için yazın...' : 'sk-proj-...' }}"
                            />
                            <p class="text-[10px] text-gray-500 mt-1">Anahtar güvenli olarak şifrelenir ve doğrudan OpenAI uç noktalarına iletilir.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">Kullanılacak Yapay Zekâ Modeli</label>
                            <select 
                                wire:model="openAiModelInput" 
                                class="w-full text-xs rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-950 text-gray-900 dark:text-gray-100 shadow-xs focus:border-primary-500 focus:ring-primary-500"
                            >
                                <option value="gpt-4o-mini">GPT-4o-mini (Hızlı, Ekonomik & Yüksek Performans - Tavsiye Edilen)</option>
                                <option value="gpt-4o">GPT-4o (En Yüksek Yaratıcılık & Derin Analiz)</option>
                            </select>
                        </div>
                    </div>

                    <div class="p-4 bg-gray-50 dark:bg-gray-950/60 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <button 
                            type="button" 
                            wire:click="$set('showApiKeyModal', false)" 
                            class="px-3.5 py-2 text-xs font-semibold text-gray-600 dark:text-gray-400 hover:text-gray-900"
                        >
                            Vazgeç
                        </button>

                        <button 
                            type="button" 
                            wire:click="saveOpenAiSettings" 
                            class="px-4 py-2 text-xs font-bold rounded-xl text-white bg-primary-600 hover:bg-primary-500 shadow-sm transition"
                        >
                            Ayarları Kaydet
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
