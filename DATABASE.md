# SocialFlow AI — Veritabanı Mimarisi (DATABASE.md)

Bu dokümanda sistemde yer alan tablolar, anahtarlar, indeksler ve veri modelleri açıklanmaktadır.

## 1. Çalışma Alanı, Kullanıcı ve Rol Yönetimi
- **users**: Kimlik doğrulama, süper admin, admin, editör ve izleyici rolleri.
- **workspaces**: Çoklu çalışma alanı desteği.
- **workspace_users**: Çalışma alanı - kullanıcı eşleşmesi ve çalışma alanına özel rol (`role`: `super_admin`, `admin`, `editor`, `viewer`).
- **brands**: Marka profilleri (`name`, `slug`, `website`, `slogan`, `brand_tone`, `target_audience`, `brand_language`, `seo_keywords`, `forbidden_words`, `default_cta`, `landing_urls`, `campaign_notes`). İlk marka: **Patenli Ayakkabılar** (`patenliayakkabilar.com`).

## 2. Sosyal Medya Entegrasyonları
- **social_platforms**: Desteklenen platformlar (`code`: `facebook`, `instagram`, `youtube`, `x`, `pinterest`, `tiktok`, `linkedin`, `name`, `icon`, `is_active`, `supported_features`, `rate_limit_per_day`).
- **social_accounts**: Bağlı sosyal hesaplar (`workspace_id`, `brand_id`, `platform_id`, `account_type`, `account_id`, `name`, `username`, `avatar_url`, `status`, `last_synced_at`).
- **social_account_credentials**: Güvenli şifrelenmiş kimlik bilgileri (`social_account_id`, `access_token` [encrypted], `refresh_token` [encrypted], `token_expires_at`, `token_type`, `scopes`).
- **social_account_permissions**: İzin durumu ve Meta App Review / TikTok Content Posting API onay durumları.

## 3. Video ve Medya Yönetimi
- **video_categories**: Video kategorileri.
- **videos**: Merkezi video kütüphanesi (`workspace_id`, `brand_id`, `category_id`, `title`, `description`, `original_filename`, `storage_path`, `disk`, `file_hash`, `file_size`, `duration_seconds`, `width`, `height`, `aspect_ratio`, `fps`, `mime_type`, `thumbnail_path`, `status`, `usage_count`, `last_used_at`, `is_reusable`).
- **video_variants**: Farklı platformlar için optimize edilmiş çözünürlük/en-boy oranları (örneğin 9:16 Reels/Shorts veya 16:9 YouTube).
- **video_tags**: Videolara atanan etiketler.

## 4. Oynatma Listeleri ve Döngü Motoru (Playlists & Cursor Engine)
- **playlists**: Oynatma listeleri (`workspace_id`, `brand_id`, `name`, `description`, `loop_mode`: `sequential`, `shuffle`, `priority`, `cooldown_days`, `status`, `is_circular`).
- **playlist_items**: Liste içindeki videolar, sıralama önceliği, özel aralıklar.
- **playlist_cursors**: Listenin mevcut pozisyonu (`playlist_id`, `current_index`, `current_video_id`, `cycle_count`, `last_dispatched_at`).

## 5. AI İçerik, Hashtag ve SEO
- **ai_prompt_templates**: Platforma özel prompt şablonları.
- **ai_content_generations**: Video başına platformlara özel üretilen başlık, açıklama, hashtag ve CTA içerikleri.
- **hashtags**: Merkezi hashtag havuzu (`category`, `tag`, `platform_filter`, `priority`, `usage_count`, `is_blacklisted`).
- **keyword_library**: SEO anahtar kelimeleri (`keyword`, `language`, `search_intent`, `priority`, `target_url`, `usage_count`).
- **video_hashtags** & **post_hashtags**: Çoktan çoğa hashtag ilişkileri.

## 6. Zamanlayıcı, Gönderiler ve Yayın Motoru
- **campaigns**: Kampanyalar (`name`, `start_date`, `end_date`, `status`, `target_urls`, `utm_tags`).
- **schedules**: Otomasyon zamanlama kuralları (`playlist_id`, `brand_id`, `cron_expression`, `time_of_day`, `days_of_week`, `target_platforms`, `is_active`).
- **scheduled_posts**: Planlanmış ana yayın kaydı (`video_id`, `campaign_id`, `scheduled_at`, `status`).
- **scheduled_post_targets**: Platform bazlı hedef yayın işi (`scheduled_post_id`, `social_account_id`, `platform_code`, `status`, `correlation_id`, `custom_caption`, `post_url`, `external_post_id`, `attempt_count`, `error_message`).
- **publication_attempts**: Her deneme için detaylı istek, yanıt ve hata logları (idempotency token ile korunur).

## 7. Sistem Sağlığı, Loglar ve Ayarlar
- **api_usage_logs**: Günlük API istek sayıları, rate limitler ve maliyetler.
- **system_notifications**: Panel içi bildirimler.
- **audit_logs**: Yönetici hareketleri.
- **settings**: Anahtar/değer sistem konfigürasyonları.
