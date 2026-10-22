# SocialFlow AI — API Entegrasyonları Rehberi (API_INTEGRATIONS.md)

Bu dokümanda SocialFlow AI sisteminin entegre olduğu 7 sosyal medya platformunun gereksinimleri, resmi API uçları ve yetkilendirme akışları özetlenmiştir.

---

## 1. Meta (Facebook Sayfaları & Instagram Professional)
- **API:** Meta Graph API v19.0+
- **Yetkilendirme:** OAuth 2.0 (Facebook Login for Business)
- **Scope'lar:**
  - Facebook: `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`
  - Instagram: `instagram_basic`, `instagram_content_publish`
- **Yayın Süreci:**
  1. Video container oluşturma (`POST /{ig-user-id}/media` with `media_type=REELS`)
  2. İşleme durumunu sorgulama (`GET /{container-id}?fields=status_code`)
  3. Canlı yayına alma (`POST /{ig-user-id}/media_publish?creation_id={container-id}`)

---

## 2. Google YouTube (Video & Shorts)
- **API:** YouTube Data API v3
- **Yetkilendirme:** Google OAuth 2.0
- **Scope'lar:** `https://www.googleapis.com/auth/youtube.upload`, `https://www.googleapis.com/auth/youtube.readonly`
- **Yayın Süreci:** Resumable upload session başlatma (`/upload/youtube/v3/videos?uploadType=resumable`), parçalı chunk yükleme, video durumunu sorgulama.
- **Politika:** Yinelenen (duplicate) içeriklerin otomatik yüklenmesini varsayılan olarak engelleme.

---

## 3. X (Eski Twitter)
- **API:** X API v2
- **Yetkilendirme:** OAuth 2.0 User Context (PKCE)
- **Scope'lar:** `tweet.read`, `tweet.write`, `users.read`, `offline.access`
- **Medya Yükleme:** Media Upload v1.1 / v2 chunked upload (`INIT`, `APPEND`, `FINALIZE`, `STATUS`).
- **Politika:** Karakter sınırı (280 karakter), yinelenen tweet koruması.

---

## 4. Pinterest
- **API:** Pinterest API v5
- **Yetkilendirme:** OAuth 2.0
- **Scope'lar:** `boards:read`, `pins:read`, `pins:write`
- **Yayın Süreci:** Media upload register (`POST /v5/media`), S3 hedef URL'sine doğrudan yükleme, Pin oluşturma (`POST /v5/pins`).

---

## 5. TikTok
- **API:** TikTok Content Posting API (Direct Post)
- **Yetkilendirme:** TikTok OAuth 2.0
- **Scope'lar:** `video.publish`, `video.upload`, `user.info.basic`
- **Politika:** TikTok App Review ve denetim gereksinimleri nedeniyle onaysız ortamlarda kullanıcıya "Onay Bekliyor" / "İnceleme Bekliyor" durumu sunulur.

---

## 6. LinkedIn
- **API:** LinkedIn Community Management / Posts API / Videos API
- **Yetkilendirme:** OAuth 2.0
- **Scope'lar:** `w_member_social`, `w_organization_social` (Şirket sayfaları için)
- **Yayın Süreci:** Video upload initialization (`/rest/videos?action=initializeUpload`), dosya yükleme, Post kaydı oluşturma.
