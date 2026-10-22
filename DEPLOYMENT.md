# SocialFlow AI — Canlıya Alma ve Sunucu Yönetimi (DEPLOYMENT.md)

Bu rehber, Linux / Plesk sunucularda SocialFlow AI'nin production ortamında güvenli ve kesintisiz çalışması için hazırlanmıştır.

## 1. Plesk / Nginx Yapılandırması
- **Web Root:** `/httpdocs/public` (veya ilgili vhost/public dizini)
- **PHP Sürümü:** PHP 8.2 veya 8.3 FPM
- **Bellek Limiti (`memory_limit`):** Min 512M (Büyük video yüklemeleri için)
- **Upload Limitleri:**
  - `upload_max_filesize = 500M`
  - `post_max_size = 520M`
  - `max_execution_time = 300`

## 2. Supervisor Kuyruk (Queue Worker) Yapılandırması
Kuyrukların arka planda durmaksızın çalışması için `/etc/supervisor/conf.d/socialflow-worker.conf` dosyasını oluşturun:

```ini
[program:socialflow-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/vhosts/socialflow.ai/httpdocs/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=socialflow
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/vhosts/socialflow.ai/httpdocs/storage/logs/worker.log
stopwaitsecs=3600
```

Güncellemek ve başlatmak için:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start socialflow-worker:*
```

## 3. Laravel Scheduler (Cron Job)
Plesk Cron Görevleri veya Linux Crontab'a şu satırı ekleyin:
```bash
* * * * * cd /var/www/vhosts/socialflow.ai/httpdocs && php artisan schedule:run >> /dev/null 2>&1
```

## 4. Redis ve FFmpeg Kurulumu (Ubuntu / Debian)
```bash
sudo apt-get update
sudo apt-get install -y redis-server ffmpeg
sudo systemctl enable --now redis-server
```

## 5. Yedekleme Stratejisi
- Veritabanı: Günlük MySQL dump
- Medya Deposu: `storage/app/public/videos` dizininin S3 veya harici sunucuya rsync ile periyodik yedeklenmesi.
