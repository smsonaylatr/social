# SocialFlow AI — Kurulum Rehberi (SETUP.md)

## Gereksinimler
- PHP 8.2 veya üzeri
- MySQL 8.0+ veya SQLite
- Composer 2.x
- Node.js 18+ & NPM
- Redis (Önerilen)
- FFmpeg & FFprobe (Video metadata ve thumbnail üretimi için)

## Adım Adım Kurulum

### 1. Depoyu Klonlama ve Bağımlılıkları Yükleme
```bash
git clone <repo-url> oto-bot
cd oto-bot
composer install
npm install && npm run build
```

### 2. Çevre Değişkenleri (.env) Yapılandırması
`.env.example` dosyasını `.env` olarak kopyalayın:
```bash
cp .env.example .env
php artisan key:generate
```

`.env` dosyasında veritabanı ayarlarını düzenleyin:
```ini
APP_NAME="SocialFlow AI"
APP_TIMEZONE="Europe/Istanbul"
APP_LOCALE="tr"

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=socialflow_ai
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
CACHE_STORE=database
```

### 3. Veritabanını Hazırlama ve Tohumlama
```bash
php artisan migrate --seed
```

### 4. Depolama Bağlantısını Oluşturma
```bash
php artisan storage:link
```

### 5. Süper Admin Oluşturma
```bash
php artisan socialflow:create-admin --name="Yönetici" --email="admin@socialflow.ai" --password="Password123!"
```

### 6. Geliştirme Sunucularını Başlatma
```bash
php artisan serve
php artisan queue:work
```
Admin Paneli: `http://localhost:8000/admin`
