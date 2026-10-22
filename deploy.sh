#!/usr/bin/env bash
# ==============================================================================
# SocialFlow AI — Production Deployment & Update Script
# ==============================================================================
set -e

echo "🚀 [1/7] Bakım modu aktifleştiriliyor..."
php artisan down || true

echo "📦 [2/7] Composer bağımlılıkları yükleniyor (no-dev, optimized)..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "🗄️ [3/7] Veritabanı tabloları güncelleniyor (migrate)..."
php artisan migrate --force

echo "🌱 [4/7] Başlangıç verileri güncelleniyor (seeder)..."
php artisan db:seed --class=InitialSystemSeeder --force

echo "🔗 [5/7] Storage symlink doğrulanıyor..."
php artisan storage:link || true

echo "⚡ [6/7] Önbellekler optimize ediliyor (config, routes, views, filament)..."
php artisan optimize:clear
php artisan optimize
php artisan filament:upgrade

echo "🔄 [7/7] Queue worker ve Supervisor yeniden başlatılıyor..."
php artisan queue:restart || true

echo "✅ Uygulama yayına alınıyor..."
php artisan up

echo "🎉 Deployment başarıyla tamamlandı!"
