# SocialFlow AI — Güvenlik Politikası ve Standartları (SECURITY.md)

SocialFlow AI, hassas sosyal medya token'ları, API anahtarları ve kullanıcı verilerini korumak için sıkı güvenlik prensiplerine uyar:

## 1. Token ve Kimlik Bilgilerinin Saklanması
- Tüm OAuth `access_token` ve `refresh_token` değerleri veritabanında Laravel'in `Crypt` (AES-256-CBC) şifrelemesiyle saklanır.
- Asla düz metin (plain text) olarak kaydedilmez.
- JSON response ve Filament arayüzlerinde secret değerleri maskelenir (`****`).

## 2. API ve OAuth Güvenliği
- OAuth 2.0 isteklerinde rastgele üretilmiş `state` parametresi ile CSRF koruması sağlanır.
- Desteklenen platformlarda (örneğin X / Twitter) PKCE (Proof Key for Code Exchange) kullanılır.
- Minimum gerekli scope prensibi uygulanır.

## 3. Yetkilendirme ve Çoklu Kiracılık (Multi-Tenancy)
- Her sorgu aktif `workspace_id` ve `brand_id` kapsamında çalıştırılır.
- Kullanıcıların kendi çalışma alanları dışındaki verilere veya kimlik bilgilerine erişimi Policy katmanında engellenir.

## 4. Dosya Yükleme Güvenliği
- Yalnızca geçerli video MIME türleri (`video/mp4`, `video/quicktime`, `video/webm`, `video/x-msvideo`) kabul edilir.
- Dosyalar rastgele hash'lenmiş isimlerle depolanır; kullanıcı kaynaklı dosya adları doğrudan dosya sistemi yolu olarak kullanılmaz.
- Yüklenen dosyaların hash (SHA-256) değeri hesaplanarak mükerrerlik ve bütünlük kontrol edilir.

## 5. Log Güvenliği
- `api_usage_logs` ve `audit_logs` tablolarında `Authorization`, `access_token`, `client_secret` ve parola parametreleri otomatik olarak maskelenir.
