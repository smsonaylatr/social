@php
    $guides = \App\Support\SocialPlatformSetupGuide::all();
@endphp

<div style="display:flex; flex-direction:column; gap:18px; color:#e4e4e7; font-size:13px;">
    <div style="padding:14px 16px; border-radius:12px; background:rgba(37,99,235,0.12); border:1px solid rgba(59,130,246,0.3); color:#bfdbfe;">
        <div style="font-weight:800; font-size:14px; color:#ffffff; margin-bottom:4px;">
            🔐 Resmi OAuth 2.0 & Erişim Kodu (Access Token) Kurulum Kılavuzu
        </div>
        <div style="font-size:12.5px; line-height:1.5; color:#93c5fd;">
            Aşağıda sistemde bulunan <strong>9 sosyal medya platformunun</strong> tamamı için doğrudan <strong>Erişim Kodu (Access Token) Alma</strong> bağlantıları, geliştirici konsolu adresleri, uygulamalarınıza eklemeniz gereken <strong>Redirect URI</strong> adresleri ve adım adım kurulum talimatları yer almaktadır.
        </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:16px;">
        @foreach($guides as $code => $g)
            <div style="border-radius:14px; border:1px solid rgba(255,255,255,0.12); background:#111318; padding:18px;">
                {{-- Header --}}
                <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; padding-bottom:12px; margin-bottom:14px; border-bottom:1px solid rgba(255,255,255,0.08);">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:{{ $g['color'] }};"></span>
                        <div>
                            <div style="font-size:15px; font-weight:800; color:#ffffff;">{{ $g['name'] }}</div>
                            <div style="font-size:11.5px; color:#a1a1aa;">{{ $g['api_name'] }}</div>
                        </div>
                    </div>

                    <div class="guide-actions-bar" style="display:flex; flex-wrap:wrap; gap:8px;">
                        <a href="{{ $g['token_generator_url'] }}" target="_blank" rel="noopener noreferrer"
                           class="guide-btn guide-btn-primary"
                           style="display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:8px 12px; border-radius:8px; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#ffffff; font-size:12px; font-weight:700; text-decoration:none;">
                            <span>🔑 {{ $g['token_generator_label'] }}</span>
                            <span>↗</span>
                        </a>
                        <a href="{{ $g['oauth_url'] }}"
                           class="guide-btn guide-btn-success"
                           style="display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:8px 12px; border-radius:8px; background:rgba(16,185,129,0.15); border:1px solid rgba(16,185,129,0.35); color:#34d399; font-size:12px; font-weight:700; text-decoration:none;">
                            <span>⚡ OAuth 2.0 İznine Git</span>
                        </a>
                        <a href="{{ $g['developer_portal_url'] }}" target="_blank" rel="noopener noreferrer"
                           class="guide-btn guide-btn-neutral"
                           style="display:inline-flex; align-items:center; justify-content:center; gap:6px; padding:8px 12px; border-radius:8px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.14); color:#e4e4e7; font-size:12px; font-weight:600; text-decoration:none;">
                            <span>🛠️ {{ $g['developer_portal_label'] }}</span>
                            <span>↗</span>
                        </a>
                    </div>
                </div>

                {{-- URIs & Scopes --}}
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:10px; margin-bottom:12px; padding:10px 12px; border-radius:10px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06);">
                    <div>
                        <div style="font-size:10.5px; font-weight:700; color:#9ca3af; text-transform:uppercase; margin-bottom:4px;">OAuth Redirect URI</div>
                        <code style="display:block; padding:5px 8px; border-radius:6px; background:#090a0f; color:#38bdf8; font-size:11.5px; font-family:monospace; user-select:all; word-break:break-all; overflow-wrap:anywhere;">{{ $g['redirect_uri'] }}</code>
                    </div>
                    <div>
                        <div style="font-size:10.5px; font-weight:700; color:#9ca3af; text-transform:uppercase; margin-bottom:4px;">Webhook Callback URL</div>
                        <code style="display:block; padding:5px 8px; border-radius:6px; background:#090a0f; color:#34d399; font-size:11.5px; font-family:monospace; user-select:all; word-break:break-all; overflow-wrap:anywhere;">{{ $g['webhook_uri'] }}</code>
                    </div>
                    <div>
                        <div style="font-size:10.5px; font-weight:700; color:#9ca3af; text-transform:uppercase; margin-bottom:4px;">Gerekli İzinler (Scopes)</div>
                        <div style="display:flex; flex-wrap:wrap; gap:4px;">
                            @foreach($g['scopes'] as $scope)
                                <code style="padding:2px 6px; border-radius:5px; background:rgba(59,130,246,0.15); border:1px solid rgba(59,130,246,0.3); color:#93c5fd; font-size:11px; font-family:monospace; word-break:break-all;">{{ $scope }}</code>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Step by step instructions --}}
                <div style="margin-bottom:8px; font-size:12px; color:#a1a1aa;">
                    <strong style="color:#f4f4f5;">Harici ID Bilgisi:</strong> {{ $g['id_field_hint'] }}
                </div>
                <div style="background:rgba(0,0,0,0.25); border-radius:9px; padding:10px 14px;">
                    <div style="font-size:12px; font-weight:700; color:#fbbf24; margin-bottom:6px;">📋 Adım Adım Nasıl Bağlanır?</div>
                    <ul style="list-style:none; margin:0; padding:0;">
                        @foreach($g['steps'] as $step)
                            <li style="padding:4px 0; font-size:12px; color:#d4d4d8; line-height:1.5; border-bottom:1px dashed rgba(255,255,255,0.06);">
                                {{ $step }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endforeach
    </div>
</div>
