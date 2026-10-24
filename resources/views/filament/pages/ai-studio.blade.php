<x-filament-panels::page>
    <div class="ais-container">
        @php
            $video = $this->selectedVideo;
            $allVideos = $this->videoList;
            $platformsCount = count($this->availablePlatforms);
        @endphp

        {{-- 1. Üst Kontrol & Video Seçim Paneli (Hero Bar) --}}
        <div class="ais-hero">
            {{-- Sol: Video Seçimi, Navigasyon ve Rozetler --}}
            <div class="ais-hero-left">
                <div class="ais-hero-label-row">
                    <div class="ais-hero-label">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect>
                            <line x1="7" y1="2" x2="7" y2="22"></line>
                            <line x1="17" y1="2" x2="17" y2="22"></line>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <line x1="2" y1="7" x2="7" y2="7"></line>
                            <line x1="2" y1="17" x2="7" y2="17"></line>
                            <line x1="17" y1="17" x2="22" y2="17"></line>
                            <line x1="17" y1="7" x2="22" y2="7"></line>
                        </svg>
                        <span>İçerik Üretilecek Video</span>
                        <span class="ais-count-pill">{{ $allVideos->count() }} Video Mevcut</span>
                    </div>

                    <div class="ais-nav-arrows">
                        <button 
                            type="button"
                            wire:click="previousVideo"
                            @disabled(! $this->hasPreviousVideo)
                            class="ais-arrow-btn"
                            title="Önceki Video"
                        >
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
                        </button>
                        <button 
                            type="button"
                            wire:click="nextVideo"
                            @disabled(! $this->hasNextVideo)
                            class="ais-arrow-btn"
                            title="Sonraki Video"
                        >
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
                        </button>
                    </div>
                </div>

                <div>
                    <select wire:model.live="selectedVideoId" class="ais-select">
                        <option value="">-- Bir Video Seçin --</option>
                        @foreach($allVideos as $v)
                            <option value="{{ $v->id }}">
                                {{ $v->title }} ({{ $v->formatted_duration }} - {{ $v->aspect_ratio }})
                            </option>
                        @endforeach
                    </select>
                </div>

                @if($video)
                    <div class="ais-meta-row">
                        <span class="ais-meta-badge">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                            <span>Format: <strong>{{ $video->aspect_ratio }}</strong></span>
                        </span>

                        <span class="ais-meta-badge">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            <span>Süre: <strong>{{ $video->formatted_duration }}</strong></span>
                        </span>

                        <span class="ais-meta-badge">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                            <span>Marka: <strong>{{ $video->brand?->name ?? 'Patenli Ayakkabılar' }}</strong></span>
                        </span>

                        <span class="ais-meta-badge badge-emerald">
                            <span style="width:6px; height:6px; border-radius:50%; background:#10b981; display:inline-block;"></span>
                            <span>Mevcut Durum: {{ $video->status->label() }}</span>
                        </span>

                        @php
                            $readyDrafts = count(array_filter($platformContents, fn($p) => !empty($p['caption'])));
                        @endphp
                        @if($readyDrafts > 0)
                            <span class="ais-meta-badge badge-indigo">
                                <span>✨ {{ $readyDrafts }}/{{ $platformsCount }} Platform Hazır</span>
                            </span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Sağ: AI Üretim Butonu ve API Ayarları --}}
            <div class="ais-hero-right">
                <button 
                    type="button"
                    wire:click="generateAiContent"
                    wire:loading.attr="disabled"
                    class="ais-btn-magic"
                >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="#fde047" style="flex-shrink:0;">
                        <path d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z"/>
                    </svg>
                    <span wire:loading.remove wire:target="generateAiContent">{{ $platformsCount }} Platform İçin Akıllı İçerik Üret</span>
                    <span wire:loading wire:target="generateAiContent">Yapay Zekâ Analiz Ediyor...</span>
                </button>

                <div class="ais-hero-subrow">
                    <button 
                        type="button"
                        wire:click="$set('showApiKeyModal', true)"
                        class="ais-sublink-btn"
                    >
                        @if($this->isOpenAiConfigured)
                            <span style="width:7px; height:7px; border-radius:50%; background:#10b981; display:inline-block;"></span>
                            <span style="color:#10b981;">OpenAI Aktif ({{ $this->maskedApiKey }})</span>
                        @else
                            <span style="width:7px; height:7px; border-radius:50%; background:#f59e0b; display:inline-block;"></span>
                            <span style="color:#f59e0b;">API Anahtarı Tanımla</span>
                        @endif
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    </button>
                    <span>•</span>
                    <button 
                        type="button" 
                        wire:click="saveAllPlatformContents" 
                        class="ais-sublink-btn"
                    >
                        Tümünü Kaydet
                    </button>
                </div>
            </div>
        </div>

        @if($this->selectedVideoId)
            {{-- 2. Ana Çalışma Paneli --}}
            <div class="ais-panel">
                {{-- Üst Kanal Filtresi Çubuğu --}}
                <div class="ais-channel-bar">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span style="font-weight:700; text-transform:uppercase; letter-spacing:0.04em;">Yayın Kanalları:</span>
                        <span class="ais-count-pill">{{ $platformsCount }} Platform</span>
                    </div>

                    <div style="display:flex; align-items:center; gap:1.25rem;">
                        <label style="display:inline-flex; align-items:center; gap:0.45rem; cursor:pointer; user-select:none; font-weight:600;">
                            <input 
                                type="checkbox" 
                                wire:model.live="onlyConnected"
                                style="width:15px; height:15px; border-radius:4px; accent-color:#6366f1; cursor:pointer;"
                            />
                            <span>Yalnızca Bağlı Hesapları Göster</span>
                        </label>

                        <a 
                            href="{{ route('filament.admin.pages.social-channels') }}" 
                            style="display:inline-flex; align-items:center; gap:0.3rem; color:#6366f1; font-weight:700; text-decoration:none;"
                        >
                            <span>Hesapları Yönet</span>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                        </a>
                    </div>
                </div>

                {{-- Platform Sekme Şeridi --}}
                <div class="ais-tabs-strip">
                    @foreach($this->availablePlatforms as $key => $tabInfo)
                        @php
                            $isActive = ($activeTab === $key);
                            $hasContent = !empty($platformContents[$key]['caption']);
                            $isConnected = !empty($tabInfo['is_connected']);
                        @endphp
                        <button 
                            type="button"
                            wire:click="$set('activeTab', '{{ $key }}')"
                            class="ais-tab-btn {{ $isActive ? 'is-active' : '' }}"
                        >
                            {{-- Platform Resmi Marka Logosu --}}
                            @if($key === 'instagram')
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="flex-shrink:0;">
                                    <rect x="2" y="2" width="20" height="20" rx="6" stroke="#E1306C" stroke-width="2.2"/>
                                    <circle cx="12" cy="12" r="4.5" stroke="#E1306C" stroke-width="2.2"/>
                                    <circle cx="17.5" cy="6.5" r="1.3" fill="#E1306C"/>
                                </svg>
                            @elseif($key === 'youtube')
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#FF0000" style="flex-shrink:0;">
                                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                </svg>
                            @elseif($key === 'facebook')
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#1877F2" style="flex-shrink:0;">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                            @elseif($key === 'x')
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;">
                                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                                </svg>
                            @elseif($key === 'tiktok')
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;">
                                    <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-1.01-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.24 1.07-.14 1.61.24 1.64 1.82 2.89 3.5 2.76 1.44-.04 2.7-1.03 3.06-2.42.14-.54.2-1.1.19-1.66.02-5.28.01-10.56.01-15.84z"/>
                                </svg>
                            @elseif($key === 'linkedin')
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="#0A66C2" style="flex-shrink:0;">
                                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451c.979 0 1.778-.773 1.778-1.729V1.73C24 .774 23.205 0 22.222 0h.003z"/>
                                </svg>
                            @elseif($key === 'pinterest')
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#E60023" style="flex-shrink:0;">
                                    <path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 0 1 .083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/>
                                </svg>
                            @endif

                            <span>{{ $tabInfo['name'] }}</span>

                            @if($isConnected)
                                <span class="ais-tab-badge-conn">Bağlı</span>
                            @else
                                <span class="ais-tab-badge-off">Bağlantı Yok</span>
                            @endif

                            @if($hasContent)
                                <span style="width:6px; height:6px; border-radius:50%; background:#6366f1; display:inline-block;" title="Taslak Mevcut"></span>
                            @endif
                        </button>
                    @endforeach
                </div>

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

                {{-- 3. İki Kolonlu Stüdyo Çalışma Alanı --}}
                <div 
                    x-data="{
                        copiedCaption: false,
                        copiedHook: false,
                        copiedTags: false,
                        copyToClipboard(text, type) {
                            navigator.clipboard.writeText(text);
                            if (type === 'caption') { this.copiedCaption = true; setTimeout(() => this.copiedCaption = false, 2000); }
                            if (type === 'hook') { this.copiedHook = true; setTimeout(() => this.copiedHook = false, 2000); }
                            if (type === 'tags') { this.copiedTags = true; setTimeout(() => this.copiedTags = false, 2000); }
                        }
                    }"
                    class="ais-workspace"
                >
                    {{-- SOL KOLON: İçerik Editörü --}}
                    <div class="ais-editor-col">
                        
                        {{-- Bağlantısız Kanal Uyarısı --}}
                        @if(isset($this->availablePlatforms[$activeTab]) && empty($this->availablePlatforms[$activeTab]['is_connected']))
                            <div class="ais-alert-amber">
                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                    <span>Bu platform (<strong>{{ $this->availablePlatforms[$activeTab]['name'] }}</strong>) için aktif bir hesap bağlı değil. Taslak hazırlayabilir veya kanalı bağlayabilirsiniz.</span>
                                </div>
                                <a href="{{ route('filament.admin.pages.social-channels', ['platform' => $activeTab]) }}" class="ais-alert-btn" style="text-decoration:none;">
                                    Kanalı Bağla →
                                </a>
                            </div>
                        @endif

                        {{-- Editör Başlığı & Kopyalama Araç Çubuğu --}}
                        <div class="ais-editor-header">
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <span style="font-size:0.9rem; font-weight:700;">{{ $this->availablePlatforms[$activeTab]['name'] }} İçerik Editörü</span>
                                @if(!empty($cur['ai_model']))
                                    <span class="ais-count-pill">
                                        {{ (str_contains($cur['ai_model'], 'test-mode') || str_contains($cur['ai_model'], 'smart-library')) ? '✨ Akıllı SEO Motoru' : $cur['ai_model'] }}
                                    </span>
                                @endif
                            </div>

                            <button 
                                type="button"
                                x-on:click="copyToClipboard(@js($captionText . ' ' . ($cur['hashtags'] ?? '')), 'caption')"
                                class="ais-copy-btn"
                            >
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                <span x-show="!copiedCaption">Metni Kopyala</span>
                                <span x-show="copiedCaption" style="color:#10b981; font-weight:700; display:none;">✓ Kopyalandı!</span>
                            </button>
                        </div>

                        {{-- Platform Başlığı (YouTube, Pinterest, Facebook için) --}}
                        @if(!empty($cfg['has_title']))
                            <div class="ais-field-group">
                                <div class="ais-label-row">
                                    <label class="ais-label">
                                        {{ $cfg['title_label'] }}
                                        @if(!empty($cfg['title_required']))
                                            <span style="color:#f43f5e;">*</span>
                                        @endif
                                    </label>
                                    @if(!empty($cfg['title_max']))
                                        <span style="font-size:0.72rem; font-family:monospace; color:#71717a;">
                                            {{ mb_strlen($cur['title'] ?? '') }} / {{ $cfg['title_max'] }}
                                        </span>
                                    @endif
                                </div>
                                <input 
                                    type="text" 
                                    wire:model.live.debounce.300ms="platformContents.{{ $activeTab }}.title" 
                                    class="ais-input"
                                    placeholder="{{ $cfg['title_placeholder'] }}"
                                    maxlength="{{ $cfg['title_max'] ?? 255 }}"
                                />
                            </div>
                        @endif

                        {{-- İlk 3 Saniye Kancası (Hook) Kartı --}}
                        @if(!empty($cfg['has_hook']))
                            <div class="ais-hook-card">
                                <div class="ais-label-row">
                                    <label class="ais-label" style="display:inline-flex; align-items:center; gap:0.4rem;">
                                        <span style="width:7px; height:7px; border-radius:50%; background:#f59e0b; display:inline-block;"></span>
                                        <span>{{ $cfg['hook_label'] }}</span>
                                    </label>
                                    <button 
                                        type="button"
                                        x-on:click="copyToClipboard(@js($cur['hook_sentence'] ?? ''), 'hook')"
                                        style="background:transparent; border:none; font-size:0.72rem; font-weight:700; color:#f59e0b; cursor:pointer; padding:0;"
                                    >
                                        <span x-show="!copiedHook">Kancayı Kopyala</span>
                                        <span x-show="copiedHook" style="color:#10b981; display:none;">✓ Kopyalandı</span>
                                    </button>
                                </div>
                                <input 
                                    type="text" 
                                    wire:model.live.debounce.300ms="platformContents.{{ $activeTab }}.hook_sentence" 
                                    class="ais-input"
                                    placeholder="{{ $cfg['hook_placeholder'] }}"
                                />
                                <span style="font-size:0.7rem; color:#a1a1aa;">
                                    💡 İzleyicinin videoda kalmasını sağlayan, ekranda veya ilk saniyelerde vurgulanacak açılış cümlesi.
                                </span>
                            </div>
                        @endif

                        {{-- Açıklama / Gönderi Metni (Caption) --}}
                        <div class="ais-field-group">
                            <div class="ais-label-row">
                                <label class="ais-label">{{ $cfg['caption_label'] }}</label>
                                <span style="font-size:0.72rem; font-family:monospace; color:{{ $isOverLimit ? '#f43f5e' : ($charPercent > 85 ? '#f59e0b' : '#71717a') }}; font-weight:{{ $isOverLimit ? '700' : '500' }};">
                                    {{ $charCount }} / {{ $maxChar }} karakter
                                </span>
                            </div>

                            <div class="ais-progress-track">
                                <div 
                                    class="ais-progress-fill" 
                                    style="width: {{ $charPercent }}%; background: {{ $isOverLimit ? '#f43f5e' : ($charPercent > 85 ? '#f59e0b' : '#6366f1') }};"
                                ></div>
                            </div>

                            <textarea 
                                rows="{{ $activeTab === 'x' ? 4 : 6 }}" 
                                wire:model.live.debounce.300ms="platformContents.{{ $activeTab }}.caption"
                                class="ais-textarea"
                                placeholder="{{ $cfg['caption_placeholder'] }}"
                            ></textarea>
                        </div>

                        {{-- Hashtag'ler ve Call To Action (2 Kolon) --}}
                        <div class="ais-grid-2">
                            <div class="ais-field-group">
                                <div class="ais-label-row">
                                    <label class="ais-label">{{ $cfg['hashtags_label'] }}</label>
                                    <button 
                                        type="button"
                                        x-on:click="copyToClipboard(@js($cur['hashtags'] ?? ''), 'tags')"
                                        style="background:transparent; border:none; font-size:0.7rem; font-weight:700; color:#6366f1; cursor:pointer; padding:0;"
                                    >
                                        <span x-show="!copiedTags">Etiketleri Kopyala</span>
                                        <span x-show="copiedTags" style="color:#10b981; display:none;">✓ Kopyalandı</span>
                                    </button>
                                </div>
                                <input 
                                    type="text" 
                                    wire:model.live.debounce.300ms="platformContents.{{ $activeTab }}.hashtags" 
                                    class="ais-input"
                                    placeholder="{{ $cfg['hashtags_placeholder'] }}"
                                />

                                @php
                                    $tagsRaw = $cur['hashtags'] ?? '';
                                    $parsedTags = array_filter(array_map('trim', explode(' ', str_replace(['#', ','], ' ', $tagsRaw))));
                                @endphp
                                @if(!empty($parsedTags))
                                    <div class="ais-chip-list">
                                        @foreach(array_slice($parsedTags, 0, 6) as $tag)
                                            <span class="ais-hashtag-chip">#{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="ais-field-group">
                                <div class="ais-label-row">
                                    <label class="ais-label">{{ $cfg['cta_label'] }}</label>
                                </div>
                                <input 
                                    type="text" 
                                    wire:model.live.debounce.300ms="platformContents.{{ $activeTab }}.call_to_action" 
                                    class="ais-input"
                                    placeholder="Hemen inceleyin veya sipariş verin..."
                                />
                                <span style="font-size:0.68rem; color:#71717a;">İzleyiciyi profile, yoruma veya mağazaya yönlendiren çağrı.</span>
                            </div>
                        </div>

                        {{-- Hedef URL & Platform Notu --}}
                        <div class="ais-field-group">
                            <div class="ais-label-row">
                                <label class="ais-label">{{ $cfg['url_label'] }}</label>
                                @if(!empty($cur['suggested_product_url']))
                                    <a 
                                        href="{{ $cur['suggested_product_url'] }}" 
                                        target="_blank" 
                                        style="font-size:0.72rem; color:#6366f1; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:0.25rem;"
                                    >
                                        <span>Linki Test Et</span>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                    </a>
                                @endif
                            </div>
                            <input 
                                type="url" 
                                wire:model.live.debounce.300ms="platformContents.{{ $activeTab }}.suggested_product_url" 
                                class="ais-input"
                                style="font-family:monospace; font-size:0.78rem;"
                                placeholder="https://patenliayakkabilar.com"
                            />
                            @if(!empty($cfg['url_note']))
                                <div class="ais-info-note">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2" style="flex-shrink:0;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                    <span>{{ $cfg['url_note'] }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Alt Kaydetme ve Durum Çubuğu --}}
                        <div class="ais-footer-bar">
                            <div style="display:flex; align-items:center; gap:0.6rem;">
                                <label class="ais-label">Onay Durumu:</label>
                                <select 
                                    wire:model="platformContents.{{ $activeTab }}.status" 
                                    class="ais-select"
                                    style="width:auto; padding:0.4rem 1.8rem 0.4rem 0.65rem; font-size:0.78rem;"
                                >
                                    <option value="draft">Taslak</option>
                                    <option value="approved">Onaylandı (Yayına Hazır)</option>
                                    <option value="rejected">Reddedildi</option>
                                </select>
                            </div>

                            <button 
                                type="button"
                                wire:click="savePlatformContent('{{ $activeTab }}')"
                                class="ais-btn-save"
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                <span>{{ ucfirst($activeTab) }} İçeriğini Kaydet</span>
                            </button>
                        </div>
                    </div>

                    {{-- SAĞ KOLON: Canlı Sosyal Medya Önizlemesi --}}
                    <div class="ais-preview-col">
                        <div style="display:flex; align-items:center; justify-content:space-between; padding:0 0.25rem;">
                            <div style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.78rem; font-weight:700;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6366f1" stroke-width="2"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"></rect><line x1="12" y1="18" x2="12.01" y2="18"></line></svg>
                                <span>Canlı Sosyal Önizleme</span>
                            </div>
                            <span class="ais-count-pill">{{ $this->availablePlatforms[$activeTab]['name'] }}</span>
                        </div>

                        @if(in_array($activeTab, ['instagram', 'tiktok', 'youtube']))
                            {{-- Dikey Mobil Video Simülatörü (Reels / TikTok / Shorts) --}}
                            <div class="ais-phone-frame">
                                <div class="ais-phone-screen">
                                    @if($video && $video->url)
                                        <video 
                                            src="{{ $video->url }}" 
                                            poster="{{ $video->thumbnail_url }}"
                                            muted 
                                            playsinline 
                                            loop 
                                            autoplay
                                            class="ais-phone-media"
                                        ></video>
                                    @elseif($video && $video->thumbnail_url)
                                        <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" class="ais-phone-media" />
                                    @endif

                                    {{-- Üst Bar --}}
                                    <div class="ais-phone-top">
                                        <span>@if($activeTab === 'instagram') Reels @elseif($activeTab === 'tiktok') Sizin İçin @else Shorts @endif</span>
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
                                    </div>

                                    {{-- Ortada Kanca (Hook Sticker) --}}
                                    @if(!empty($cur['hook_sentence']))
                                        <div class="ais-phone-hook-wrap">
                                            <div class="ais-phone-hook-pill">
                                                {{ $cur['hook_sentence'] }}
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Alt Bilgi ve Sağ Aksiyon Sütunu --}}
                                    <div class="ais-phone-bottom">
                                        <div class="ais-phone-caption-box">
                                            <div style="display:flex; align-items:center; gap:0.4rem;">
                                                <div style="width:22px; height:22px; border-radius:50%; background:linear-gradient(135deg,#f59e0b,#e11d48); display:flex; align-items:center; justify-content:center; font-size:0.65rem; font-weight:800; color:#fff;">
                                                    {{ strtoupper(substr($activeUsername, 0, 1)) }}
                                                </div>
                                                <span style="font-size:0.75rem; font-weight:700;">{{ '@' . $activeUsername }}</span>
                                            </div>

                                            @if(!empty($cur['title']))
                                                <div style="font-size:0.73rem; font-weight:800; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                    {{ $cur['title'] }}
                                                </div>
                                            @endif

                                            <div style="font-size:0.7rem; line-height:1.35; color:#e4e4e7; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden;">
                                                {{ $captionText ?: 'İçerik açıklaması burada canlı olarak önizlenir...' }}
                                            </div>

                                            @if(!empty($cur['hashtags']))
                                                <div style="font-size:0.68rem; font-weight:600; color:#38bdf8; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                                    {{ $cur['hashtags'] }}
                                                </div>
                                            @endif

                                            <div style="display:flex; align-items:center; gap:0.35rem; font-size:0.65rem; color:#d4d4d8; margin-top:0.15rem;">
                                                <span>🎵 Orijinal Ses - {{ $activeAccountName }}</span>
                                            </div>
                                        </div>

                                        <div class="ais-phone-actions-rail">
                                            <div class="ais-rail-item">
                                                <div class="ais-rail-circle">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#f43f5e"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                                                </div>
                                                <span>14.2K</span>
                                            </div>

                                            <div class="ais-rail-item">
                                                <div class="ais-rail-circle">
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="#ffffff"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                                </div>
                                                <span>342</span>
                                            </div>

                                            <div class="ais-rail-item">
                                                <div class="ais-rail-circle">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                                                </div>
                                                <span>890</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- X / Facebook / LinkedIn / Pinterest Gönderi Kartı --}}
                            <div class="ais-feed-card">
                                <div style="display:flex; align-items:center; justify-content:space-between;">
                                    <div style="display:flex; align-items:center; gap:0.6rem;">
                                        <div style="width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,#6366f1,#8b5cf6); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; font-size:0.8rem;">
                                            {{ strtoupper(substr($activeAccountName, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div style="font-size:0.8rem; font-weight:700;">{{ $activeAccountName }}</div>
                                            <div style="font-size:0.68rem; color:#71717a;">{{ '@' . $activeUsername }} · Şimdi</div>
                                        </div>
                                    </div>
                                </div>

                                @if(!empty($cur['title']))
                                    <div style="font-size:0.85rem; font-weight:800;">{{ $cur['title'] }}</div>
                                @endif

                                <div style="font-size:0.78rem; line-height:1.5; white-space:pre-line; max-height:180px; overflow-y:auto;">
                                    {{ $captionText ?: 'Gönderi metni burada canlı olarak önizlenir...' }}
                                </div>

                                @if(!empty($cur['hashtags']))
                                    <div style="font-size:0.75rem; font-weight:600; color:#38bdf8;">
                                        {{ $cur['hashtags'] }}
                                    </div>
                                @endif

                                @if($video)
                                    <div style="position:relative; border-radius:0.75rem; overflow:hidden; background:#000; aspect-ratio:16/9; display:flex; align-items:center; justify-content:center;">
                                        @if($video->thumbnail_url)
                                            <img src="{{ $video->thumbnail_url }}" alt="{{ $video->title }}" style="width:100%; height:100%; object-fit:cover; opacity:0.85;" />
                                        @endif
                                        <span style="position:absolute; bottom:6px; right:8px; padding:2px 6px; border-radius:4px; background:rgba(0,0,0,0.75); color:#fff; font-size:0.65rem; font-family:monospace;">
                                            {{ $video->formatted_duration }}
                                        </span>
                                    </div>
                                @endif

                                @if(!empty($cur['suggested_product_url']))
                                    <div class="ais-info-note" style="justify-content:space-between;">
                                        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-family:monospace;">{{ $cur['suggested_product_url'] }}</span>
                                        <span style="color:#6366f1; font-weight:700; flex-shrink:0;">İncele →</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <x-filament::section>
                <div style="text-align:center; padding:2.5rem 1rem;">
                    <h3 style="font-size:1rem; font-weight:700; margin-bottom:0.35rem;">AI İçerik Stüdyosu'na Hoş Geldiniz</h3>
                    <p style="font-size:0.8rem; color:#71717a;">Metin ve platform optimizasyonu üretmek için lütfen yukarıdaki menüden bir video seçin.</p>
                </div>
            </x-filament::section>
        @endif

        {{-- 4. OpenAI API Anahtarı ve Model Yönetim Modalı --}}
        @if($showApiKeyModal)
            <div class="mc-modal-overlay">
                <div class="mc-modal-window" style="max-width:480px;">
                    <div class="mc-modal-header" style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);">
                        <div style="display:flex; align-items:center; gap:0.65rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                            <div>
                                <div style="font-size:0.95rem; font-weight:700;">OpenAI API Ayarları</div>
                                <div style="font-size:0.72rem; opacity:0.85;">GPT-4o & Sosyal SEO Motoru Yapılandırması</div>
                            </div>
                        </div>
                        <button type="button" wire:click="$set('showApiKeyModal', false)" class="mc-alert-close">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="mc-modal-body">
                        <div>
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.35rem;">
                                <label class="mc-form-label" style="margin-bottom:0;">OpenAI API Anahtarı (Secret Key)</label>
                                @if($this->isOpenAiConfigured)
                                    <span style="font-size:0.7rem; font-family:monospace; color:#10b981; font-weight:700;">
                                        Aktif: {{ $this->maskedApiKey }}
                                    </span>
                                @endif
                            </div>
                            <input 
                                type="password" 
                                wire:model="openAiKeyInput" 
                                class="mc-form-input"
                                style="font-family:monospace;"
                                placeholder="{{ $this->isOpenAiConfigured ? 'Yeni bir anahtar girmek için yazın...' : 'sk-proj-...' }}"
                            />
                            <div style="font-size:0.7rem; color:#71717a; margin-top:0.35rem;">
                                Anahtarınız güvenli olarak veritabanına kaydedilir ve doğrudan OpenAI API isteklerinde kullanılır.
                            </div>
                        </div>

                        <div>
                            <label class="mc-form-label">Kullanılacak Yapay Zekâ Modeli</label>
                            <select wire:model="openAiModelInput" class="mc-form-input">
                                <option value="gpt-4o-mini">GPT-4o-mini (Hızlı, Ekonomik & Yüksek Performans - Önerilen)</option>
                                <option value="gpt-4o">GPT-4o (Maksimum Yaratıcılık & Derin Analiz)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mc-modal-footer">
                        <button 
                            type="button" 
                            wire:click="$set('showApiKeyModal', false)" 
                            class="ais-copy-btn"
                        >
                            Vazgeç
                        </button>

                        <button 
                            type="button" 
                            wire:click="saveOpenAiSettings" 
                            class="ais-btn-save"
                        >
                            Ayarları Kaydet
                        </button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
