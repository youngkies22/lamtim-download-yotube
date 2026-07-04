#!/bin/sh
set -e

cd /var/www/html

echo "==> Menyiapkan aplikasi Laravel..."

# 1. Pastikan file .env ada
if [ ! -f .env ]; then
    echo "==> .env tidak ada, menyalin dari .env.example"
    cp .env.example .env
fi

# 2. Pastikan APP_KEY terisi
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "==> Membuat APP_KEY baru"
    php artisan key:generate --force
fi

# 3. Pastikan database SQLite ada
if [ ! -f database/database.sqlite ]; then
    echo "==> Membuat database SQLite kosong"
    touch database/database.sqlite
fi

# 4. Pastikan folder runtime & tmp download tersedia
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/app/tmp \
    storage/logs \
    bootstrap/cache

# 5. Jalankan migrasi (buat tabel sessions, cache, jobs, users)
echo "==> Menjalankan migrasi database"
php artisan migrate --force

# 6. Optimalkan konfigurasi & view (route:cache dilewati karena ada closure route)
php artisan config:cache
php artisan view:cache

# 7. Set kepemilikan agar php-fpm (www-data) bisa menulis
chown -R www-data:www-data storage bootstrap/cache database
chmod -R ug+rw storage bootstrap/cache database

echo "==> Selesai. Menjalankan server..."

# Jalankan proses utama (supervisor: php-fpm + nginx + scheduler)
exec "$@"
