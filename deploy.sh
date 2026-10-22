#!/usr/bin/env bash
# ==============================================================================
# SocialFlow AI — Production Deployment & Update Script
# ==============================================================================
set -e

echo "📥 [1/8] En güncel kodlar Git'ten çekiliyor..."
git pull origin main || true

echo "🚀 [2/8] Bakım modu aktifleştiriliyor..."
php artisan down || true

echo "📦 [3/8] Composer bağımlılıkları yükleniyor (no-dev, optimized)..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "🗄️ [4/8] Veritabanı tabloları güncelleniyor (migrate)..."
php artisan migrate --force

echo "🌱 [5/8] Başlangıç verileri güncelleniyor (seeder)..."
php artisan db:seed --class=InitialSystemSeeder --force

echo "🔗 [6/8] Storage symlink doğrulanıyor..."
php artisan storage:link || true

echo "⚡ [7/8] Önbellekler optimize ediliyor (config, routes, views, filament)..."
php artisan optimize:clear
php artisan optimize
php artisan filament:upgrade

echo "🔄 [8/8] Queue worker ve Supervisor yeniden başlatılıyor..."
php artisan queue:restart || true

echo "✅ Uygulama yayına alınıyor..."
php artisan up

echo "🎉 Deployment başarıyla tamamlandı!"
