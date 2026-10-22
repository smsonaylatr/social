<div class="space-y-6 text-xs text-gray-700 dark:text-gray-300">
    <div class="p-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900 rounded-lg text-blue-900 dark:text-blue-300">
        <p class="font-semibold text-sm mb-1">OAuth 2.0 ve API Yetkilendirme Standartları</p>
        <p>SocialFlow AI yalnızca resmi geliştirici portalları ve onaylı OAuth API protokolleri üzerinden çalışır. Şifre veya gayriresmi bot yöntemleri kesinlikle kullanılmaz.</p>
    </div>

    <div class="space-y-4">
        {{-- Meta Facebook & Instagram --}}
        <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
            <h4 class="font-bold text-sm text-gray-900 dark:text-gray-100 mb-1 flex items-center justify-between">
                <span>1. Meta (Facebook Sayfaları & Instagram Reels)</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-300 font-mono">Graph API v19.0+</span>
            </h4>
            <p class="mb-2">Developer Portalı: <a href="https://developers.facebook.com" target="_blank" class="text-primary-600 dark:text-primary-400 underline font-mono">developers.facebook.com</a></p>
            <ul class="list-disc list-inside space-y-1 text-[11px] text-gray-600 dark:text-gray-400">
                <li><strong>Gerekli İzinler (Scopes):</strong> <code>pages_show_list</code>, <code>pages_read_engagement</code>, <code>pages_manage_posts</code>, <code>instagram_basic</code>, <code>instagram_content_publish</code></li>
                <li><strong>Gelişmiş Erişim (App Review):</strong> Production ortamında herkese açık paylaşım için Meta App Review başvurusu zorunludur.</li>
                <li><strong>Hesap Türü:</strong> Instagram hesabının "Professional" (İşletme veya İçerik Üretici) olması ve Facebook Sayfasına bağlı olması gerekir.</li>
            </ul>
        </div>

        {{-- Google YouTube --}}
        <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
            <h4 class="font-bold text-sm text-gray-900 dark:text-gray-100 mb-1 flex items-center justify-between">
                <span>2. Google YouTube (Video & Shorts)</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300 font-mono">YouTube Data API v3</span>
            </h4>
            <p class="mb-2">Google Cloud Console: <a href="https://console.cloud.google.com" target="_blank" class="text-primary-600 dark:text-primary-400 underline font-mono">console.cloud.google.com</a></p>
            <ul class="list-disc list-inside space-y-1 text-[11px] text-gray-600 dark:text-gray-400">
                <li><strong>Gerekli Scope:</strong> <code>https://www.googleapis.com/auth/youtube.upload</code></li>
                <li><strong>Shorts Politikası:</strong> Shorts için ayrı bir API yoktur; süresi 60 saniyeden kısa ve dikey (9:16) videolar YouTube tarafından otomatik Shorts olarak işlenir.</li>
                <li><strong>Kota Yönetimi:</strong> Her video yükleme işlemi 1600 YouTube API kotası tüketir. Günlük kota sınırına dikkat edilmelidir.</li>
            </ul>
        </div>

        {{-- X (Twitter) --}}
        <div class="p-3 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
            <h4 class="font-bold text-sm text-gray-900 dark:text-gray-100 mb-1 flex items-center justify-between">
                <span>3. X (Twitter)</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 font-mono">X API v2</span>
            </h4>
            <p class="mb-2">X Developer Portal: <a href="https://developer.twitter.com" target="_blank" class="text-primary-600 dark:text-primary-400 underline font-mono">developer.twitter.com</a></p>
            <ul class="list-disc list-inside space-y-1 text-[11px] text-gray-600 dark:text-gray-400">
                <li><strong>Gerekli Scope:</strong> <code>tweet.read</code>, <code>tweet.write</code>, <code>users.read</code>, <code>offline.access</code></li>
                <li><strong>Limitler:</strong> Karakter sınırı 280'dir. Yinelenen (duplicate) tweet paylaşımı X spam filtrelerince yasaklanmıştır.</li>
            </ul>
        </div>

        {{-- Pinterest, TikTok, LinkedIn --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="p-2.5 rounded-lg border border-gray-200 dark:border-gray-800">
                <h5 class="font-bold text-gray-900 dark:text-gray-100 mb-1">Pinterest</h5>
                <p class="text-[11px] text-gray-600 dark:text-gray-400">Scopes: <code>boards:read</code>, <code>pins:write</code>. Video pin için pano seçimi gereklidir.</p>
            </div>
            <div class="p-2.5 rounded-lg border border-gray-200 dark:border-gray-800">
                <h5 class="font-bold text-gray-900 dark:text-gray-100 mb-1">TikTok</h5>
                <p class="text-[11px] text-gray-600 dark:text-gray-400">Content Posting API: <code>video.publish</code> scope ve ticari hesap onayı gerekir.</p>
            </div>
            <div class="p-2.5 rounded-lg border border-gray-200 dark:border-gray-800">
                <h5 class="font-bold text-gray-900 dark:text-gray-100 mb-1">LinkedIn</h5>
                <p class="text-[11px] text-gray-600 dark:text-gray-400">Scopes: <code>w_member_social</code>, <code>w_organization_social</code> (şirket sayfası için).</p>
            </div>
        </div>
    </div>
</div>
