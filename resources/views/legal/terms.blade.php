<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - {{ config('app.name', 'SocialFlow AI') }}</title>
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
        <h1>Terms of Service</h1>
        <p class="date">Effective Date: October 2026 | Last Updated: October 2026</p>
    </div>

    <h2>1. Acceptance of Terms</h2>
    <p>
        By accessing or using <strong>{{ config('app.name', 'SocialFlow AI') }}</strong> ("Service"), you agree to be bound by these Terms of Service. If you disagree with any part of these terms, you may not access the Service.
    </p>

    <h2>2. Description of Service</h2>
    <p>
        {{ config('app.name', 'SocialFlow AI') }} is an intelligent social media automation platform designed to assist users in preparing, generating AI metadata for, and scheduling video content across social networks, including YouTube, Instagram, TikTok, Facebook, and X.
    </p>

    <h2>3. Compliance with Third-Party Platform Terms</h2>
    <p>
        Our Service integrates with third-party APIs, including the YouTube API Services. By connecting your third-party social media accounts, you expressly agree to comply with their respective terms of service:
    </p>
    <ul>
        <li><a href="https://www.youtube.com/t/terms" target="_blank" rel="noopener">YouTube Terms of Service</a></li>
        <li><a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google Privacy Policy</a></li>
        <li><a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Google API Services User Data Policy</a></li>
    </ul>

    <h2>4. User Responsibilities & Content Guidelines</h2>
    <p>
        You are solely responsible for the content, media, titles, and descriptions you schedule and publish through {{ config('app.name', 'SocialFlow AI') }}. You represent and warrant that:
    </p>
    <ul>
        <li>You hold all necessary rights, licenses, and permissions for any video or media you upload.</li>
        <li>Your content does not violate any copyright, intellectual property, privacy, or community guidelines of third-party platforms (including YouTube Community Guidelines).</li>
        <li>You will not use the Service for spam, malicious activities, or distribution of illegal materials.</li>
    </ul>

    <h2>5. Termination & Access Revocation</h2>
    <p>
        We reserve the right to suspend or terminate your access to the Service at our sole discretion, without notice, for conduct that violates these Terms. You may terminate your use of the Service at any time by disconnecting your connected accounts or revoking authorization in your Google Account Security settings.
    </p>

    <h2>6. Limitation of Liability</h2>
    <p>
        The Service is provided on an "AS IS" and "AS AVAILABLE" basis. {{ config('app.name', 'SocialFlow AI') }} shall not be liable for any indirect, incidental, or consequential damages resulting from third-party API downtime, account restrictions imposed by third-party platforms, or loss of content.
    </p>

    <h2>7. Contact</h2>
    <p>
        For inquiries regarding these Terms of Service, please contact:
        <br>
        <strong>Email:</strong> <a href="mailto:patenliayakkabilar@gmail.com">patenliayakkabilar@gmail.com</a> (or <a href="mailto:support@radioai.digital">support@radioai.digital</a>)
        <br>
        <strong>Website:</strong> <a href="https://radioai.digital">https://radioai.digital</a>
    </p>

    <div class="footer">
        <p>&copy; {{ date('Y') }} {{ config('app.name', 'SocialFlow AI') }}. All rights reserved.</p>
        <p><a href="/terms-of-service">Terms of Service</a> &bull; <a href="/privacy-policy">Privacy Policy</a></p>
    </div>
</div>
</body>
</html>
