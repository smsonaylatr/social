<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - {{ config('app.name', 'SocialFlow AI') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --border: #334155;
            --highlight: #0284c7;
        }
        @media (prefers-color-scheme: light) {
            :root {
                --bg-color: #f8fafc;
                --card-bg: #ffffff;
                --text-primary: #0f172a;
                --text-secondary: #475569;
                --accent: #2563eb;
                --accent-hover: #1d4ed8;
                --border: #e2e8f0;
                --highlight: #0284c7;
            }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            line-height: 1.7;
            padding: 40px 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 48px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        .header {
            border-bottom: 1px solid var(--border);
            padding-bottom: 24px;
            margin-bottom: 32px;
        }
        .brand {
            display: inline-block;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--accent);
            text-decoration: none;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        h1 {
            font-size: 2.2rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }
        .date {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        .lang-switch {
            margin-top: 16px;
            display: flex;
            gap: 12px;
        }
        .lang-btn {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text-secondary);
            padding: 6px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s;
        }
        .lang-btn.active, .lang-btn:hover {
            border-color: var(--accent);
            color: var(--text-primary);
            background: rgba(59, 130, 246, 0.1);
        }
        h2 {
            font-size: 1.35rem;
            margin-top: 36px;
            margin-bottom: 12px;
            color: var(--text-primary);
            border-left: 4px solid var(--accent);
            padding-left: 12px;
        }
        p {
            margin-bottom: 16px;
            color: var(--text-secondary);
        }
        ul {
            margin-bottom: 20px;
            padding-left: 24px;
            color: var(--text-secondary);
        }
        li {
            margin-bottom: 8px;
        }
        .alert-box {
            background: rgba(59, 130, 246, 0.08);
            border: 1px solid var(--accent);
            border-radius: 10px;
            padding: 18px 22px;
            margin: 24px 0;
            color: var(--text-primary);
            font-size: 0.95rem;
        }
        a {
            color: var(--accent);
            text-decoration: underline;
        }
        a:hover {
            color: var(--accent-hover);
        }
        .footer {
            margin-top: 48px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <a href="/" class="brand">{{ config('app.name', 'SocialFlow AI') }}</a>
        <h1>Privacy Policy</h1>
        <p class="date">Effective Date: October 2026 | Last Updated: October 2026</p>
        <div class="lang-switch">
            <a href="#english" class="lang-btn active">English</a>
            <a href="#turkish" class="lang-btn">Türkçe</a>
        </div>
    </div>

    <!-- ENGLISH SECTION -->
    <div id="english">
        <h2>1. Introduction</h2>
        <p>
            Welcome to <strong>{{ config('app.name', 'SocialFlow AI') }}</strong> ("we", "our", or "us"). We are committed to protecting your personal data and privacy. 
            This Privacy Policy explains how our platform accesses, uses, stores, and protects information when you connect and authorize your third-party social media accounts, including <strong>Google and YouTube</strong>.
        </p>

        <h2>2. Google API Services & YouTube User Data</h2>
        <div class="alert-box">
            <strong>Google API Services User Data Policy Compliance:</strong><br>
            {{ config('app.name', 'SocialFlow AI') }}'s use and transfer to any other app of information received from Google APIs adheres to the 
            <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a>, including the <strong>Limited Use</strong> requirements.
        </div>
        <p>When you link your YouTube account to {{ config('app.name', 'SocialFlow AI') }} via Google OAuth 2.0, we request access to the following permissions (scopes):</p>
        <ul>
            <li><code>https://www.googleapis.com/auth/youtube.upload</code>: Used solely to publish and upload videos, titles, descriptions, and tags that you have scheduled and approved in the {{ config('app.name', 'SocialFlow AI') }} dashboard directly to your authorized YouTube channel.</li>
            <li><code>https://www.googleapis.com/auth/youtube.readonly</code>: Used to retrieve your channel name, channel identifier, and upload status to confirm successful video scheduling and view publication logs.</li>
        </ul>

        <h2>3. Data Collection and Usage</h2>
        <p>We only collect and process data that is strictly necessary for providing the social media management service:</p>
        <ul>
            <li><strong>OAuth Credentials:</strong> Secure access tokens and refresh tokens provided by Google to interact with your channel on your instruction.</li>
            <li><strong>Channel Metadata:</strong> YouTube channel name, channel ID, and avatar to identify your account inside our dashboard.</li>
            <li><strong>Video Media & Content:</strong> The videos, descriptions, and titles uploaded by you to be distributed to your connected platforms.</li>
        </ul>
        <p>
            <strong>We DO NOT:</strong>
        </p>
        <ul>
            <li>We do NOT sell, lease, or distribute your Google/YouTube user data to any third parties.</li>
            <li>We do NOT use your private data, channel videos, or personal content to train generalized artificial intelligence or machine learning models.</li>
            <li>We do NOT share your data with advertisers or data brokers.</li>
        </ul>

        <h2>4. Data Storage and Security</h2>
        <p>
            All OAuth tokens (access tokens and refresh tokens) are encrypted at rest using industry-standard AES-256 encryption. 
            All network communication occurs over encrypted Transport Layer Security (HTTPS/TLS). Access to our servers and databases is strictly limited to authorized personnel.
        </p>

        <h2>5. Data Retention and Revoking Access</h2>
        <p>
            You have full control over your data at all times:
        </p>
        <ul>
            <li><strong>Disconnecting from Dashboard:</strong> You can disconnect your YouTube or Google account at any time in the Social Channels settings within {{ config('app.name', 'SocialFlow AI') }}. Upon disconnection, all associated access and refresh tokens are permanently removed from our active database.</li>
            <li><strong>Revoking Access via Google:</strong> You can revoke our application's access at any time through Google's official security settings page at: 
                <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">https://myaccount.google.com/permissions</a>.
            </li>
            <li><strong>Data Deletion Request:</strong> If you wish to delete all data associated with your account, please contact us at the support email provided below.</li>
        </ul>

        <h2>6. Third-Party Links & YouTube Terms of Service</h2>
        <p>
            Our service uses YouTube API Services. By using our service to upload videos to YouTube, you also agree to be bound by the 
            <a href="https://www.youtube.com/t/terms" target="_blank" rel="noopener">YouTube Terms of Service</a> and 
            <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google Privacy Policy</a>.
        </p>

        <h2>7. Contact Information</h2>
        <p>
            If you have questions, concerns, or requests regarding this Privacy Policy, please contact our data protection team at:
            <br>
            <strong>Support Email:</strong> <a href="mailto:patenliayakkabilar@gmail.com">patenliayakkabilar@gmail.com</a> (or <a href="mailto:support@radioai.digital">support@radioai.digital</a>)
            <br>
            <strong>Website:</strong> <a href="https://radioai.digital">https://radioai.digital</a>
        </p>
    </div>

    <!-- TURKISH SECTION -->
    <div id="turkish" style="margin-top: 50px; padding-top: 40px; border-top: 2px dashed var(--border);">
        <h2>Gizlilik Politikası (Türkçe)</h2>
        <p>
            <strong>{{ config('app.name', 'SocialFlow AI') }}</strong> ("biz", "bizim"), kullanıcılarımızın gizliliğine ve kişisel verilerinin korunmasına büyük önem vermektedir.
            Bu Gizlilik Politikası, platformumuza <strong>Google ve YouTube</strong> hesaplarınızı bağladığınızda toplanan, işlenen ve korunan verileri açıklamaktadır.
        </p>

        <h2>Google API ve YouTube Verilerinin Kullanımı</h2>
        <div class="alert-box">
            {{ config('app.name', 'SocialFlow AI') }}, Google API'leri aracılığıyla alınan bilgileri 
            <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Hizmetleri Kullanıcı Verisi Politikası</a>'na (Sınırlı Kullanım / Limited Use gereksinimleri dahil) tam uyumlu olarak işler ve aktarır.
        </div>
        <p>Google OAuth 2.0 üzerinden istenen YouTube izinleri ve amaçları:</p>
        <ul>
            <li><code>youtube.upload</code>: Yalnızca kullanıcının kendi isteği ve onayıyla panel üzerinden planladığı videoları, başlık ve açıklamaları YouTube kanalına yüklemek için kullanılır.</li>
            <li><code>youtube.readonly</code>: Yükleme durumunu doğrulamak, kanal adı ve kimliğini panelde görüntülemek için kullanılır.</li>
        </ul>
        <p>
            Verileriniz hiçbir surette üçüncü taraflara satılmaz, reklam ağlarıyla paylaşılmaz ve genel yapay zeka modellerini eğitmek amacıyla kullanılmaz.
            Erişim yetkisini dilediğiniz an <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">Google Güvenlik İzinleri</a> sayfasından veya sistem panelimizden iptal edebilirsiniz.
        </p>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('app.name', 'SocialFlow AI') }}. All rights reserved.</p>
        <p><a href="/terms-of-service">Terms of Service</a> &bull; <a href="/privacy-policy">Privacy Policy</a></p>
    </div>
</div>
</body>
</html>
