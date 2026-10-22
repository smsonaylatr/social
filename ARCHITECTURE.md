# SocialFlow AI — Sistem Mimarisi

SocialFlow AI, çoklu marka (workspace/brand) destekli, yapay zekâ destekli video içerik ve döngüsel sosyal medya otomasyon platformudur.

## 1. Mimari Prensipler
- **Modülerlik ve Genişletilebilirlik:** Sosyal medya platformları bağımsız connector/adapter yapılarıyla `SocialPublisherInterface` sözleşmesini uygular.
- **Fail-Safe & Idempotency:** Her yayın girişimi bağımsız correlation ID, idempotency token ve durum makinesi (state machine) ile takip edilir.
- **Temiz Kod (Clean Architecture):**
  - **Controllers / Filament Resources:** Yalnızca UI ve istek yönlendirme.
  - **Services / Actions:** İş kuralları ve harici servis orkestrasyonu.
  - **Jobs:** Dağıtık ve zamanlanmış kuyruk işlemleri (exponential backoff ve jitter desteği ile).
  - **DTOs & Enums:** Tip güvenliği ve veri aktarım tutarlılığı.
  - **Policies:** Rol ve çalışma alanı bazlı yetkilendirme.

## 2. Teknoloji Katmanları
- **Çekirdek:** PHP 8.2+ / Laravel 12
- **Admin & Dashboard:** Filament v3, Livewire, Alpine.js, Tailwind CSS
- **Veritabanı:** MySQL 8+ (Geliştirme ve testte SQLite/MySQL desteği)
- **Kuyruk & Önbellek:** Redis / Database Queue, Laravel Horizon desteği
- **Medya İşleme:** FFmpeg, FFprobe, GD/ImageMagick
- **AI Motoru:** OpenAI GPT-4o / GPT-4o-mini structured output entegrasyonu (genişletilebilir provider mimarisi)

## 3. Sosyal Medya Platform Adaptörleri
Tüm platformlar `App\Services\Social\Contracts\SocialPublisherInterface` sözleşmesini uygular:
- Meta Facebook Graph API (Pages, Video & Reels)
- Meta Instagram Graph API (Professional/Business Reels & Videos)
- Google YouTube Data API v3 (Video & Shorts)
- X (Twitter) API v2 (Chunked upload & Tweet creation)
- Pinterest API v5 (Video Pins & Boards)
- TikTok Content Posting API (Direct post & Creator verification)
- LinkedIn API (Community & Posts API for personal/company pages)

## 4. Döngüsel Yayın Motoru (Circular Playlist Engine)
- İmleç (Cursor) tabanlı durum takibi.
- Platform bazlı yinelenen içerik koruma politikaları (YouTube & X gibi platformlarda tekrar kuralı engelleme).
- Akıllı aralık (Cooldown period) ve içerik hash doğrulama.
