<x-filament-panels::page>
    <div class="mc-page-container">

        {{-- Manychat Style Green / Info Notification Banner --}}
        @if($showBanner && $bannerMessage)
            <div class="mc-alert-banner {{ $bannerType === 'success' ? 'mc-alert-success' : ($bannerType === 'warning' ? 'mc-alert-warning' : 'mc-alert-info') }}">
                <div class="mc-alert-content">
                    @if($bannerType === 'success')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="#ffffff" style="flex-shrink:0;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                    @elseif($bannerType === 'warning')
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="#ffffff" style="flex-shrink:0;"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                    @else
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="#ffffff" style="flex-shrink:0;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
                    @endif
                    <span>{{ $bannerMessage }}</span>
                </div>
                <button type="button" wire:click="dismissBanner" class="mc-alert-close" title="Kapat">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        {{-- Main Two-Column Side-by-Side Layout (Manychat Split View) --}}
        <div class="channels-wrapper">

            {{-- Left Column: Channels List (Vertical Nav) --}}
            <div class="channels-nav-panel">
                <div class="channels-nav-header">
                    <span class="channels-nav-title">Channels (Kanallar)</span>
                    <span class="channels-count-badge">
                        {{ $this->platformsStatus->where('is_connected', true)->count() }} / {{ $this->platformsStatus->count() }} Bağlı
                    </span>
                </div>

                <div class="channels-nav-list">
                    @foreach($this->platformsStatus as $code => $p)
                        @php
                            $isActive = ($activePlatform === $code);
                        @endphp
                        <button
                            type="button"
                            wire:click="selectPlatform('{{ $code }}')"
                            class="channel-tab-button {{ $isActive ? 'active-channel' : '' }}"
                        >
                            <div class="channel-tab-left">
                                {{-- Platform Icon with explicit SVG attributes --}}
                                <div class="channel-icon-box" style="background: {{ $code === 'instagram' ? 'radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%)' : $p['color'] }};">
                                    @if($code === 'tiktok')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                            <path d="M13.1 1.5h3.4c.2 1.5.9 2.9 2 3.8 1 .9 2.3 1.4 3.7 1.6v3.4c-1.3-.1-2.6-.4-3.7-1-.5-.2-1-.6-1.4-.9v7.8c-.1 1.3-.5 2.5-1.2 3.5-1.2 1.7-3.2 2.8-5.3 2.9-1.3.1-2.5-.3-3.6-.9-1.8-1.1-3.1-3-3.2-5.1 0-.4 0-.9 0-1.3.2-1.7 1-3.3 2.3-4.4 1.5-1.3 3.5-1.9 5.5-1.5v3.9c-.9-.3-1.9-.2-2.7.3-.6.4-1 .9-1.2 1.6-.2.5-.2 1-.1 1.4.2 1.5 1.6 2.7 3.1 2.6 1 0 1.9-.6 2.5-1.4.2-.3.3-.6.4-.9.1-1.6.1-3.2.1-4.8V1.5z" fill="#25F4EE" transform="translate(-0.8, -0.6)"/>
                                            <path d="M13.1 1.5h3.4c.2 1.5.9 2.9 2 3.8 1 .9 2.3 1.4 3.7 1.6v3.4c-1.3-.1-2.6-.4-3.7-1-.5-.2-1-.6-1.4-.9v7.8c-.1 1.3-.5 2.5-1.2 3.5-1.2 1.7-3.2 2.8-5.3 2.9-1.3.1-2.5-.3-3.6-.9-1.8-1.1-3.1-3-3.2-5.1 0-.4 0-.9 0-1.3.2-1.7 1-3.3 2.3-4.4 1.5-1.3 3.5-1.9 5.5-1.5v3.9c-.9-.3-1.9-.2-2.7.3-.6.4-1 .9-1.2 1.6-.2.5-.2 1-.1 1.4.2 1.5 1.6 2.7 3.1 2.6 1 0 1.9-.6 2.5-1.4.2-.3.3-.6.4-.9.1-1.6.1-3.2.1-4.8V1.5z" fill="#FE2C55" transform="translate(0.8, 0.6)"/>
                                            <path d="M13.1 1.5h3.4c.2 1.5.9 2.9 2 3.8 1 .9 2.3 1.4 3.7 1.6v3.4c-1.3-.1-2.6-.4-3.7-1-.5-.2-1-.6-1.4-.9v7.8c-.1 1.3-.5 2.5-1.2 3.5-1.2 1.7-3.2 2.8-5.3 2.9-1.3.1-2.5-.3-3.6-.9-1.8-1.1-3.1-3-3.2-5.1 0-.4 0-.9 0-1.3.2-1.7 1-3.3 2.3-4.4 1.5-1.3 3.5-1.9 5.5-1.5v3.9c-.9-.3-1.9-.2-2.7.3-.6.4-1 .9-1.2 1.6-.2.5-.2 1-.1 1.4.2 1.5 1.6 2.7 3.1 2.6 1 0 1.9-.6 2.5-1.4.2-.3.3-.6.4-.9.1-1.6.1-3.2.1-4.8V1.5z" fill="#FFFFFF"/>
                                        </svg>
                                    @elseif($code === 'instagram')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                                    @elseif($code === 'youtube')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                                    @elseif($code === 'facebook')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                    @elseif($code === 'x')
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="#ffffff"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                    @elseif($code === 'pinterest')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff"><path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 0 1 .083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>
                                    @elseif($code === 'linkedin')
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="#ffffff"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                                    @elseif($code === 'whatsapp')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                                    @elseif($code === 'telegram')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.121l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.458c.536-.194 1.006.128.832.94z"/></svg>
                                    @endif
                                </div>

                                <div class="channel-tab-info">
                                    <span class="channel-tab-name">{{ $p['name'] }}</span>
                                    @if($p['is_connected'])
                                        <span class="channel-tab-sub is-connected">● {{ $p['accounts_count'] }} Hesap Bağlı</span>
                                    @else
                                        <span class="channel-tab-sub">○ Bağlı Değil</span>
                                    @endif
                                </div>
                            </div>

                            <div>
                                @if($p['is_connected'])
                                    <span class="channel-active-pill">Aktif</span>
                                @else
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.45;"><path d="M9 18l6-6-6-6"/></svg>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </div>

                <div class="channels-nav-footer" style="display:flex; flex-direction:column; gap:0.5rem;">
                    <button
                        type="button"
                        wire:click="connectAllPlatforms"
                        style="width:100%; display:inline-flex; align-items:center; justify-content:center; gap:0.45rem; padding:0.65rem 0.75rem; border-radius:0.65rem; font-size:0.78rem; font-weight:700; color:#ffffff; background:linear-gradient(135deg,#0084ff 0%,#2563eb 100%); border:none; cursor:pointer; box-shadow:0 4px 12px rgba(0,132,255,0.28);"
                    >
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                        <span>Tüm Platform API'lerini Bağla</span>
                    </button>
                    <a href="{{ url('/admin/social-accounts') }}" class="btn-nav-switch">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                        <span>Tüm Hesaplar (Tablo Görünümü)</span>
                    </a>
                </div>
            </div>

            {{-- Right Column: Active Platform Detail (Manychat Main Canvas) --}}
            <div class="channels-content-panel">
                @php
                    $platform = $this->selectedPlatformData;
                    $isConnected = $platform['is_connected'];
                    $accounts = $platform['accounts'];
                    $pCode = $platform['code'];
                    $setupGuide = \App\Support\SocialPlatformSetupGuide::get($pCode);
                @endphp

                {{-- Channel Header --}}
                <div class="mc-panel-header">
                    <div class="mc-panel-header-left">
                        <div class="channel-icon-box" style="background: {{ $pCode === 'instagram' ? 'radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%)' : $platform['color'] }};">
                            @if($pCode === 'tiktok')
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                    <path d="M13.1 1.5h3.4c.2 1.5.9 2.9 2 3.8 1 .9 2.3 1.4 3.7 1.6v3.4c-1.3-.1-2.6-.4-3.7-1-.5-.2-1-.6-1.4-.9v7.8c-.1 1.3-.5 2.5-1.2 3.5-1.2 1.7-3.2 2.8-5.3 2.9-1.3.1-2.5-.3-3.6-.9-1.8-1.1-3.1-3-3.2-5.1 0-.4 0-.9 0-1.3.2-1.7 1-3.3 2.3-4.4 1.5-1.3 3.5-1.9 5.5-1.5v3.9c-.9-.3-1.9-.2-2.7.3-.6.4-1 .9-1.2 1.6-.2.5-.2 1-.1 1.4.2 1.5 1.6 2.7 3.1 2.6 1 0 1.9-.6 2.5-1.4.2-.3.3-.6.4-.9.1-1.6.1-3.2.1-4.8V1.5z" fill="#25F4EE" transform="translate(-0.8, -0.6)"/>
                                    <path d="M13.1 1.5h3.4c.2 1.5.9 2.9 2 3.8 1 .9 2.3 1.4 3.7 1.6v3.4c-1.3-.1-2.6-.4-3.7-1-.5-.2-1-.6-1.4-.9v7.8c-.1 1.3-.5 2.5-1.2 3.5-1.2 1.7-3.2 2.8-5.3 2.9-1.3.1-2.5-.3-3.6-.9-1.8-1.1-3.1-3-3.2-5.1 0-.4 0-.9 0-1.3.2-1.7 1-3.3 2.3-4.4 1.5-1.3 3.5-1.9 5.5-1.5v3.9c-.9-.3-1.9-.2-2.7.3-.6.4-1 .9-1.2 1.6-.2.5-.2 1-.1 1.4.2 1.5 1.6 2.7 3.1 2.6 1 0 1.9-.6 2.5-1.4.2-.3.3-.6.4-.9.1-1.6.1-3.2.1-4.8V1.5z" fill="#FE2C55" transform="translate(0.8, 0.6)"/>
                                    <path d="M13.1 1.5h3.4c.2 1.5.9 2.9 2 3.8 1 .9 2.3 1.4 3.7 1.6v3.4c-1.3-.1-2.6-.4-3.7-1-.5-.2-1-.6-1.4-.9v7.8c-.1 1.3-.5 2.5-1.2 3.5-1.2 1.7-3.2 2.8-5.3 2.9-1.3.1-2.5-.3-3.6-.9-1.8-1.1-3.1-3-3.2-5.1 0-.4 0-.9 0-1.3.2-1.7 1-3.3 2.3-4.4 1.5-1.3 3.5-1.9 5.5-1.5v3.9c-.9-.3-1.9-.2-2.7.3-.6.4-1 .9-1.2 1.6-.2.5-.2 1-.1 1.4.2 1.5 1.6 2.7 3.1 2.6 1 0 1.9-.6 2.5-1.4.2-.3.3-.6.4-.9.1-1.6.1-3.2.1-4.8V1.5z" fill="#FFFFFF"/>
                                </svg>
                            @elseif($pCode === 'instagram')
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                            @elseif($pCode === 'youtube')
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            @elseif($pCode === 'facebook')
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            @elseif($pCode === 'x')
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="#ffffff"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            @elseif($pCode === 'pinterest')
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff"><path d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 0 1 .083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></svg>
                            @elseif($pCode === 'linkedin')
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="#ffffff"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                            @elseif($pCode === 'whatsapp')
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                            @elseif($pCode === 'telegram')
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="#ffffff"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.121l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.458c.536-.194 1.006.128.832.94z"/></svg>
                            @endif
                        </div>
                        <div>
                            <h3 class="mc-panel-title">{{ $platform['name'] }}</h3>
                            <span style="font-size:0.72rem; color:#71717a;">{{ $platform['partner_program'] ?? 'Official Partner OAuth 2.0' }}</span>
                        </div>
                    </div>

                    <div style="display:flex; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                        <a href="{{ $setupGuide['token_generator_url'] }}" target="_blank" rel="noopener noreferrer"
                           style="display:inline-flex; align-items:center; gap:0.35rem; padding:0.42rem 0.85rem; border-radius:0.55rem; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#ffffff; font-size:0.75rem; font-weight:700; text-decoration:none; box-shadow:0 2px 8px rgba(37,99,235,0.3);">
                            <span>🔑 Erişim Kodu (Token) Alma Sayfası</span>
                            <span>↗</span>
                        </a>
                        <a href="{{ $setupGuide['developer_portal_url'] }}" target="_blank" rel="noopener noreferrer" class="mc-docs-link">
                            <span>🛠️ Geliştirici Konsolu</span>
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/></svg>
                        </a>
                    </div>
                </div>

                {{-- Channel Body --}}
                <div class="mc-panel-body">
                    @if(! $isConnected)
                        {{-- State: Not Connected (Manychat Stage Card with Speech Bubble Illustration) --}}
                        <div class="mc-stage-card">
                            
                            {{-- Authentic Manychat Speech Bubble & Brand Badge SVG Illustration --}}
                            <div class="mc-illustration-wrap">
                                <svg width="168" height="148" viewBox="0 0 168 148" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <defs>
                                        <clipPath id="bubbleClip">
                                            <path d="M36 52C36 28.804 54.804 10 78 10H116C139.196 10 158 28.804 158 52V78C158 101.196 139.196 120 116 120H114L126 142C110 140 97 131 92 120H78C54.804 120 36 101.196 36 78V52Z" />
                                        </clipPath>
                                        <linearGradient id="igGrad" x1="0" y1="1" x2="1" y2="0">
                                            <stop offset="0%" stop-color="#fdf497"/>
                                            <stop offset="45%" stop-color="#fd5949"/>
                                            <stop offset="65%" stop-color="#d6249f"/>
                                            <stop offset="100%" stop-color="#285AEB"/>
                                        </linearGradient>
                                    </defs>

                                    {{-- Speech Bubble Checkered Background --}}
                                    <g clip-path="url(#bubbleClip)">
                                        <rect x="30" y="0" width="140" height="150" fill="#8CE2E6"/>
                                        <rect x="72" y="0" width="44" height="42" fill="#FF1CF7"/>
                                        <rect x="30" y="42" width="42" height="42" fill="#FF1CF7"/>
                                        <rect x="116" y="42" width="46" height="42" fill="#FF1CF7"/>
                                        <rect x="72" y="84" width="44" height="65" fill="#FF1CF7"/>
                                        <rect x="116" y="84" width="46" height="65" fill="#8CE2E6"/>
                                    </g>

                                    {{-- Foreground Floating Brand Tile --}}
                                    <rect x="16" y="34" width="76" height="76" rx="20" transform="rotate(-4 16 34)" fill="{{ $pCode === 'instagram' ? 'url(#igGrad)' : ($pCode === 'tiktok' ? '#09090b' : $platform['color']) }}" stroke="rgba(255,255,255,0.22)" stroke-width="2"/>

                                    {{-- Brand Icon Centered Inside Tile --}}
                                    <g transform="translate(36, 52) rotate(-4 18 18)">
                                        @if($pCode === 'tiktok')
                                            <path d="M19.5 2.2h5.1c.3 2.3 1.4 4.3 3 5.7 1.5 1.3 3.5 2.1 5.5 2.4v5.1c-2-.1-3.9-.6-5.5-1.5-.8-.4-1.5-.9-2.1-1.4v11.7c-.1 1.9-.7 3.7-1.8 5.2-1.8 2.5-4.8 4.2-8 4.3-1.9.1-3.8-.4-5.4-1.4-2.7-1.6-4.6-4.5-4.8-7.6 0-.6 0-1.3 0-2 .3-2.5 1.5-4.9 3.4-6.6 2.2-1.9 5.3-2.8 8.2-2.2v5.8c-1.3-.4-2.8-.3-4 .4-.9.6-1.5 1.4-1.8 2.4-.3.7-.3 1.5-.2 2.1.3 2.2 2.4 4 4.6 3.9 1.5 0 2.9-.9 3.7-2.1.3-.4.5-.9.6-1.4.1-2.4.1-4.8.1-7.2V2.2z" fill="#25F4EE" transform="scale(0.95) translate(-1.2, -1)"/>
                                            <path d="M19.5 2.2h5.1c.3 2.3 1.4 4.3 3 5.7 1.5 1.3 3.5 2.1 5.5 2.4v5.1c-2-.1-3.9-.6-5.5-1.5-.8-.4-1.5-.9-2.1-1.4v11.7c-.1 1.9-.7 3.7-1.8 5.2-1.8 2.5-4.8 4.2-8 4.3-1.9.1-3.8-.4-5.4-1.4-2.7-1.6-4.6-4.5-4.8-7.6 0-.6 0-1.3 0-2 .3-2.5 1.5-4.9 3.4-6.6 2.2-1.9 5.3-2.8 8.2-2.2v5.8c-1.3-.4-2.8-.3-4 .4-.9.6-1.5 1.4-1.8 2.4-.3.7-.3 1.5-.2 2.1.3 2.2 2.4 4 4.6 3.9 1.5 0 2.9-.9 3.7-2.1.3-.4.5-.9.6-1.4.1-2.4.1-4.8.1-7.2V2.2z" fill="#FE2C55" transform="scale(0.95) translate(1.2, 1)"/>
                                            <path d="M19.5 2.2h5.1c.3 2.3 1.4 4.3 3 5.7 1.5 1.3 3.5 2.1 5.5 2.4v5.1c-2-.1-3.9-.6-5.5-1.5-.8-.4-1.5-.9-2.1-1.4v11.7c-.1 1.9-.7 3.7-1.8 5.2-1.8 2.5-4.8 4.2-8 4.3-1.9.1-3.8-.4-5.4-1.4-2.7-1.6-4.6-4.5-4.8-7.6 0-.6 0-1.3 0-2 .3-2.5 1.5-4.9 3.4-6.6 2.2-1.9 5.3-2.8 8.2-2.2v5.8c-1.3-.4-2.8-.3-4 .4-.9.6-1.5 1.4-1.8 2.4-.3.7-.3 1.5-.2 2.1.3 2.2 2.4 4 4.6 3.9 1.5 0 2.9-.9 3.7-2.1.3-.4.5-.9.6-1.4.1-2.4.1-4.8.1-7.2V2.2z" fill="#FFFFFF" transform="scale(0.95)"/>
                                        @elseif($pCode === 'instagram')
                                            <g transform="scale(1.5)"><path fill="#ffffff" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></g>
                                        @elseif($pCode === 'youtube')
                                            <g transform="scale(1.5)"><path fill="#ffffff" d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></g>
                                        @elseif($pCode === 'facebook')
                                            <g transform="scale(1.5)"><path fill="#ffffff" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></g>
                                        @elseif($pCode === 'x')
                                            <g transform="scale(1.5)"><path fill="#ffffff" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></g>
                                        @elseif($pCode === 'pinterest')
                                            <g transform="scale(1.5)"><path fill="#ffffff" d="M12 0C5.373 0 0 5.372 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 0 1 .083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.631-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12 0-6.628-5.373-12-12-12z"/></g>
                                        @elseif($pCode === 'linkedin')
                                            <g transform="scale(1.5)"><path fill="#ffffff" d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></g>
                                        @elseif($pCode === 'whatsapp')
                                            <g transform="scale(1.5)"><path fill="#ffffff" d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></g>
                                        @elseif($pCode === 'telegram')
                                            <g transform="scale(1.5)"><path fill="#ffffff" d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221l-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.446 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.121l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.458c.536-.194 1.006.128.832.94z"/></g>
                                        @endif
                                    </g>
                                </svg>
                            </div>

                            <h2 class="mc-hero-title">{{ $platform['name'] }}</h2>
                            <p class="mc-hero-tagline">{{ $platform['tagline'] }}</p>
                            <p class="mc-hero-desc">{{ $platform['description'] }}</p>

                            {{-- Manychat "Connect" Button --}}
                            <button
                                type="button"
                                wire:click="openConnectModal('{{ $platform['code'] }}')"
                                class="manychat-btn-connect"
                            >
                                <span>Connect</span>
                            </button>
                        </div>

                        {{-- Scope / Permissions Grid Below Stage Card --}}
                        <div class="mc-scopes-section">
                            <div class="mc-section-label">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                <span>İş Ortağı Uygulaması İle Alınacak Erişim İzinleri (Partner OAuth Scopes)</span>
                            </div>

                            <div class="mc-scopes-grid">
                                @foreach($platform['scopes'] as $scope)
                                    <div class="mc-scope-card">
                                        <div class="mc-scope-header">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="#10b981" style="flex-shrink:0;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                                            <span class="mc-scope-title">{{ $scope['label'] }}</span>
                                        </div>
                                        <div>
                                            <span class="mc-scope-code">{{ $scope['name'] }}</span>
                                        </div>
                                        <div class="mc-scope-desc">{{ $scope['description'] }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                    @else
                        {{-- State: Connected (Display Account Cards & Actions) --}}
                        <div>
                            <div class="mc-connected-topbar">
                                <div style="display:flex; align-items:center; gap:0.75rem;">
                                    <span class="mc-status-badge-online">
                                        <span style="width:8px; height:8px; border-radius:9999px; background:#10b981; display:inline-block;"></span>
                                        <span>Erişim İzni Aktif ({{ $accounts->count() }} Hesap Bağlı)</span>
                                    </span>
                                </div>

                                <button
                                    type="button"
                                    wire:click="openConnectModal('{{ $platform['code'] }}')"
                                    class="mc-btn-action"
                                >
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                                    <span>Yeni {{ $platform['name'] }} Hesabı Bağla</span>
                                </button>
                            </div>

                            {{-- Connected Accounts List --}}
                            @foreach($accounts as $acc)
                                <div class="mc-account-card">
                                    <div class="mc-account-row">
                                        <div class="mc-account-identity">
                                            <div class="mc-account-avatar" style="background: {{ $pCode === 'instagram' ? 'radial-gradient(circle at 30% 107%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%)' : $platform['color'] }};">
                                                {{ strtoupper(substr($acc->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <div style="display:flex; align-items:center; gap:0.5rem;">
                                                    <strong style="font-size:1rem; font-weight:700;">{{ $acc->name }}</strong>
                                                    <span class="mc-scope-code" style="text-transform:uppercase;">{{ $acc->account_type }}</span>
                                                </div>
                                                <div style="font-size:0.78rem; color:#71717a; margin-top:4px; display:flex; flex-wrap:wrap; gap:0.65rem;">
                                                    <span>Kullanıcı: <strong>{{ '@' . ($acc->username ?: $acc->account_id) }}</strong></span>
                                                    <span>•</span>
                                                    <span>ID: <code>{{ $acc->account_id }}</code></span>
                                                    <span>•</span>
                                                    <span>Yetki: <strong style="color:#10b981;">OAuth 2.0 İş Ortağı Erişimi</strong></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mc-account-actions">
                                            <button
                                                type="button"
                                                wire:click="refreshPermissions({{ $acc->id }})"
                                                wire:loading.attr="disabled"
                                                class="mc-btn-action mc-btn-refresh"
                                                title="Erişim iznini ve OAuth jetonunu yenile"
                                            >
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6M3 12a9 9 0 0 1 15-6.7L21 8M3 22v-6h6M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
                                                <span>İzinleri Yenile</span>
                                            </button>

                                            <button
                                                type="button"
                                                wire:click="openEditModal({{ $acc->id }})"
                                                class="mc-btn-action"
                                                title="Kanal Adı ve ID Düzenle"
                                            >
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                                                <span>Kanalı Düzenle</span>
                                            </button>

                                            <button
                                                type="button"
                                                wire:click="confirmDisconnect({{ $acc->id }})"
                                                class="mc-btn-action mc-btn-danger"
                                                title="Bağlantıyı Kes"
                                            >
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                                <span>Bağlantıyı Kes</span>
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Granted Permissions Row --}}
                                    <div style="margin-top:1rem; padding-top:0.85rem; border-top:1px solid rgba(128,128,128,0.15); display:flex; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                                        <span style="font-size:0.7rem; font-weight:700; text-transform:uppercase; color:#71717a;">Aktif Erişim İzinleri:</span>
                                        @forelse($acc->permissions as $perm)
                                            <span class="mc-scope-code">✓ {{ $perm->permission_name }}</span>
                                        @empty
                                            @foreach($platform['scopes'] as $scope)
                                                <span class="mc-scope-code">✓ {{ $scope['name'] }}</span>
                                            @endforeach
                                        @endforelse
                                        <span style="font-size:0.72rem; color:#71717a; margin-left:auto;">
                                            Son Doğrulama: {{ $acc->last_synced_at ? $acc->last_synced_at->diffForHumans() : 'Az önce' }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Step-by-Step Setup Guide & Direct Token Links Card --}}
                    <div style="margin-top:1.25rem; border-radius:0.9rem; border:1px solid rgba(255,255,255,0.1); background:#111318; padding:1.25rem;">
                        <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:0.75rem; padding-bottom:0.9rem; margin-bottom:1rem; border-bottom:1px solid rgba(255,255,255,0.08);">
                            <div>
                                <div style="font-size:0.95rem; font-weight:800; color:#ffffff;">
                                    📋 {{ $platform['name'] }} Erişim Kodu (Token) Nasıl Alınır & Nasıl Bağlanır?
                                </div>
                                <div style="font-size:0.75rem; color:#a1a1aa; margin-top:2px;">
                                    {{ $setupGuide['api_name'] }} — Adım Adım Kurulum Kılavuzu ve Doğrudan Erişim Linkleri
                                </div>
                            </div>
                            <span style="padding:0.25rem 0.65rem; border-radius:999px; font-size:0.7rem; font-weight:700; background:rgba(59,130,246,0.15); color:#93c5fd; border:1px solid rgba(59,130,246,0.3);">
                                Kurulum Rehberi
                            </span>
                        </div>

                        {{-- 3 Direct Links --}}
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:0.65rem; margin-bottom:1rem;">
                            <a href="{{ $setupGuide['token_generator_url'] }}" target="_blank" rel="noopener noreferrer"
                               style="display:flex; align-items:center; justify-content:space-between; padding:0.7rem 0.9rem; border-radius:0.6rem; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#ffffff; font-size:0.78rem; font-weight:700; text-decoration:none; box-shadow:0 3px 10px rgba(37,99,235,0.28);">
                                <span>🔑 {{ $setupGuide['token_generator_label'] }}</span>
                                <span>↗</span>
                            </a>

                            <a href="{{ $setupGuide['oauth_url'] }}"
                               style="display:flex; align-items:center; justify-content:space-between; padding:0.7rem 0.9rem; border-radius:0.6rem; background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.38); color:#34d399; font-size:0.78rem; font-weight:700; text-decoration:none;">
                                <span>⚡ OAuth 2.0 Erişim İzni Ekranına Git</span>
                                <span>→</span>
                            </a>

                            <a href="{{ $setupGuide['developer_portal_url'] }}" target="_blank" rel="noopener noreferrer"
                               style="display:flex; align-items:center; justify-content:space-between; padding:0.7rem 0.9rem; border-radius:0.6rem; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.14); color:#e4e4e7; font-size:0.78rem; font-weight:600; text-decoration:none;">
                                <span>🛠️ {{ $setupGuide['developer_portal_label'] }}</span>
                                <span>↗</span>
                            </a>
                        </div>

                        {{-- Steps --}}
                        <div style="background:rgba(0,0,0,0.28); border-radius:0.65rem; padding:0.85rem 1rem; border:1px solid rgba(255,255,255,0.06);">
                            <div style="font-size:0.78rem; font-weight:700; color:#fbbf24; margin-bottom:0.45rem;">
                                Adım Adım Bağlama Talimatları:
                            </div>
                            <ul style="list-style:none; margin:0; padding:0;">
                                @foreach($setupGuide['steps'] as $step)
                                    <li style="padding:0.38rem 0; font-size:0.78rem; color:#d4d4d8; line-height:1.5; border-bottom:1px dashed rgba(255,255,255,0.06);">
                                        {{ $step }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    {{-- Partner App & OAuth Setup Box (For Platform Owner / Partner Application) --}}
                    <div x-data="{ open: false }" class="mc-dev-box">
                        <button type="button" @click="open = !open" class="mc-dev-toggle">
                            <span style="display:flex; align-items:center; gap:0.5rem;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                <span>{{ $platform['name'] }} İş Ortağı (Partner App) Başvuru & OAuth Yapılandırması</span>
                            </span>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                        </button>

                        <div x-show="open" style="padding: 1.25rem; border-top: 1px solid rgba(128,128,128,0.15); display:flex; flex-direction:column; gap:1rem;">
                            <div style="font-size:0.78rem; color:#a1a1aa; line-height:1.5;">
                                <strong>{{ $platform['partner_program'] ?? $platform['name'] }}</strong> iş ortaklığı başvurunuzda kullanacağınız geri dönüş adresleri aşağıdadır. Başvurunuz onaylandığında verilen <code>Client ID</code> ve <code>Client Secret</code> anahtarlarını buraya kaydederek tüm üyeleriniz için canlı OAuth yönlendirmesini aktif edebilirsiniz.
                            </div>

                            <div class="mc-form-grid">
                                <div>
                                    <label class="mc-form-label">OAuth Redirect URI (Başvuru Formu İçin):</label>
                                    <input type="text" readonly value="{{ url('/oauth/callback/' . $platform['code']) }}" class="mc-input-readonly" />
                                </div>
                                <div>
                                    <label class="mc-form-label">Webhook Bildirim Adresi (Callback URL):</label>
                                    <input type="text" readonly value="{{ url('/webhooks/social/' . $platform['code']) }}" class="mc-input-readonly" />
                                </div>
                            </div>

                            <div class="mc-form-grid" style="padding-top:0.5rem; border-top:1px solid rgba(128,128,128,0.12);">
                                <div>
                                    <label class="mc-form-label">Partner App Client ID / App Key:</label>
                                    <input type="text" wire:model="partnerClientId" class="mc-form-input" placeholder="İş ortağı portalından alınan Client ID..." />
                                </div>
                                <div>
                                    <label class="mc-form-label">Partner App Client Secret:</label>
                                    <input type="password" wire:model="partnerClientSecret" class="mc-form-input" placeholder="••••••••••••••••••••••••" />
                                </div>
                            </div>

                            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem;">
                                <div style="display:flex; align-items:center; gap:0.75rem;">
                                    <label class="mc-form-label" style="margin:0;">Bağlantı Ortamı:</label>
                                    <select wire:model="partnerEnvironment" class="mc-form-input" style="width:auto; padding:0.35rem 0.75rem;">
                                        <option value="sandbox">Başvuru / Sandbox Modu (Simülasyon İzin Onayı)</option>
                                        <option value="production">Canlı İş Ortağı Modu (Resmi OAuth Yönlendirmesi)</option>
                                    </select>
                                </div>

                                <button type="button" wire:click="savePartnerConfig" class="mc-btn-action mc-btn-refresh">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                    <span>İş Ortağı OAuth Ayarlarını Kaydet</span>
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        {{-- Official Partner OAuth Consent Modal (No Brand Field, Pure Permission Authorization) --}}
        @if($showConnectModal)
            @php
                $activeDef = $this->getPlatformDefinitions()[$connectPlatformCode] ?? $this->getPlatformDefinitions()['tiktok'];
            @endphp
            <div class="mc-modal-overlay">
                <div class="mc-modal-window">
                    
                    <div class="mc-modal-header" style="background: {{ $connectPlatformCode === 'instagram' ? 'linear-gradient(90deg, #fd5949 0%, #d6249f 50%, #285AEB 100%)' : ($connectPlatformCode === 'tiktok' ? '#18181b' : $activeDef['color']) }}; border-bottom: 1px solid rgba(255,255,255,0.12);">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div class="channel-icon-box" style="background: rgba(255,255,255,0.18);">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </div>
                            <div>
                                <div style="font-size:1rem; font-weight:800; color:#ffffff;">{{ $activeDef['name'] }} Erişim İzni & Yetkilendirme</div>
                                <div style="font-size:0.74rem; color:rgba(255,255,255,0.85);">{{ $activeDef['partner_program'] ?? 'Resmi İş Ortağı OAuth 2.0 Bağlantısı' }}</div>
                            </div>
                        </div>
                        <button type="button" wire:click="closeConnectModal" class="mc-alert-close">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="mc-modal-body">
                        {{-- Partner OAuth Trust Banner --}}
                        <div style="padding: 0.85rem 1rem; border-radius: 0.65rem; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.28); display:flex; align-items:center; gap:0.85rem;">
                            <div style="width:36px; height:36px; border-radius:0.5rem; background:#2563eb; color:#ffffff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                            </div>
                            <div style="font-size:0.77rem; line-height:1.45; color:#d4d4d8;">
                                <strong style="color:#ffffff; display:block; margin-bottom:2px;">Resmi İş Ortağı Erişim İzni (OAuth 2.0)</strong>
                                Hesap şifreniz paylaşılmaz. {{ $activeDef['name'] }} üzerinden yalnızca aşağıda seçtiğiniz yayın ve profil izinleri için güvenli erişim yetkisi tanımlanır.
                            </div>
                        </div>

                        {{-- Account Details (No Brand Field!) --}}
                        <div class="mc-form-grid">
                            <div style="grid-column: span 2;">
                                <label class="mc-form-label">Yetkilendirilecek Hesap Türü</label>
                                <select wire:model="connectAccountType" class="mc-form-input">
                                    @foreach($activeDef['account_types'] as $key => $lbl)
                                        <option value="{{ $key }}">{{ $lbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mc-form-label">Hesap / Sayfa / Kanal Başlığı</label>
                                <input type="text" wire:model="connectName" class="mc-form-input" placeholder="Örn: {{ $activeDef['name'] }} Hesabım" />
                            </div>
                            <div>
                                <label class="mc-form-label">Kullanıcı Adı (@) <span style="font-weight:400; color:#71717a;">(Opsiyonel)</span></label>
                                <input type="text" wire:model="connectUsername" class="mc-form-input" placeholder="kullanici_adi" />
                            </div>
                        </div>

                        {{-- Scopes Checklist --}}
                        <div>
                            <label class="mc-form-label">Verilecek Erişim İzinleri (Partner OAuth Scopes):</label>
                            <div class="mc-scopes-checklist">
                                @foreach($activeDef['scopes'] as $scope)
                                    <label class="mc-scope-check-item">
                                        <input type="checkbox" wire:model="selectedScopes" value="{{ $scope['name'] }}" style="margin-top:3px;" />
                                        <div>
                                            <div style="font-size:0.8rem; font-weight:700;">
                                                {{ $scope['label'] }}
                                                <span class="mc-scope-code" style="margin-left:4px;">{{ $scope['name'] }}</span>
                                            </div>
                                            <div style="font-size:0.72rem; color:#71717a; margin-top:2px;">{{ $scope['description'] }}</div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="mc-modal-footer">
                        <button type="button" wire:click="closeConnectModal" class="mc-btn-action">
                            Vazgeç
                        </button>

                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            @if(!empty($partnerClientId) && $partnerEnvironment === 'production')
                                <button type="button" wire:click="startOAuthRedirect" class="manychat-btn-connect" style="padding: 0.55rem 1.4rem; font-size:0.82rem;">
                                    <span>{{ $activeDef['name'] }} İle Yönlendir & İzin Ver</span>
                                </button>
                            @else
                                <button type="button" wire:click="connectAccount" wire:loading.attr="disabled" class="manychat-btn-connect" style="padding: 0.55rem 1.5rem; font-size:0.82rem;">
                                    <span wire:loading.remove>✓ Erişim İznini Onayla ve Bağlan</span>
                                    <span wire:loading>Yetkilendiriliyor...</span>
                                </button>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        @endif

        {{-- Disconnect Confirmation Modal --}}
        @if($showDisconnectModal)
            <div class="mc-modal-overlay">
                <div class="mc-modal-window" style="max-width: 400px;">
                    <div class="mc-modal-body" style="text-align:center; align-items:center; padding: 2rem 1.5rem;">
                        <div style="width:48px; height:48px; border-radius:9999px; background:rgba(225,29,72,0.15); color:#e11d48; display:flex; align-items:center; justify-content:center;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </div>
                        <div style="font-size:1.05rem; font-weight:800;">Erişim İznini Kaldırmak İstiyor Musunuz?</div>
                        <div style="font-size:0.8rem; color:#71717a;">
                            Bu işlem hesabın OAuth erişim yetkisini sonlandıracaktır. Planlanan gönderiler bu kanala iletilmez.
                        </div>
                        <div style="display:flex; gap:0.75rem; width:100%; margin-top:0.5rem;">
                            <button type="button" wire:click="$set('showDisconnectModal', false)" class="mc-btn-action" style="flex:1; justify-content:center;">
                                Vazgeç
                            </button>
                            <button type="button" wire:click="disconnectAccount" class="mc-btn-action mc-btn-danger" style="flex:1; justify-content:center; background:#e11d48; color:#ffffff; border-color:#e11d48;">
                                Evet, Bağlantıyı Kes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Edit Account Modal --}}
        @if($showEditModal)
            <div class="mc-modal-overlay">
                <div class="mc-modal-window" style="max-width: 520px;">
                    <div class="mc-modal-header" style="background:#18181b; border-bottom:1px solid rgba(255,255,255,0.12);">
                        <div style="display:flex; align-items:center; gap:0.75rem;">
                            <div class="channel-icon-box" style="background:rgba(255,255,255,0.15);">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            </div>
                            <div>
                                <div style="font-size:1rem; font-weight:800; color:#ffffff;">Kanal Bilgilerini Düzenle</div>
                                <div style="font-size:0.74rem; color:rgba(255,255,255,0.7);">Kanal Adı, Kullanıcı Adı ve Kanal Kimliği (UC...)</div>
                            </div>
                        </div>
                        <button type="button" wire:click="closeEditModal" class="mc-alert-close">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="mc-modal-body">
                        <div class="mc-form-grid">
                            <div style="grid-column: span 2;">
                                <label class="mc-form-label">Kanal / Hesap Adı</label>
                                <input type="text" wire:model="editName" class="mc-form-input" placeholder="Örn: Patenli Ayakkabılar" />
                            </div>
                            <div>
                                <label class="mc-form-label">Kullanıcı Adı (@)</label>
                                <input type="text" wire:model="editUsername" class="mc-form-input" placeholder="@patenliayakkabilar" />
                            </div>
                            <div>
                                <label class="mc-form-label">Kanal Kimliği (ID — UC...)</label>
                                <input type="text" wire:model="editAccountId" class="mc-form-input" placeholder="UCxxxxxxxxxxxxxxxxxxxxxx" />
                            </div>
                        </div>
                    </div>

                    <div class="mc-modal-footer">
                        <button type="button" wire:click="closeEditModal" class="mc-btn-action">
                            Vazgeç
                        </button>
                        <button type="button" wire:click="saveEditedAccount" class="manychat-btn-connect" style="padding: 0.55rem 1.4rem; font-size:0.82rem;">
                            <span>✓ Değişiklikleri Kaydet</span>
                        </button>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
