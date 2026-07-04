# syntax=docker/dockerfile:1

# =========================================================================
# Stage 1: Build aset frontend (Vite + Tailwind) menjadi public/build
# =========================================================================
FROM node:22-bookworm-slim AS assets

WORKDIR /app

# Install dependency dulu supaya layer bisa di-cache
COPY package.json package-lock.json ./
RUN npm install --no-audit --no-fund

# Copy sisa source lalu build
COPY . .
RUN npm run build


# =========================================================================
# Stage 2: Ambil binary composer siap pakai
# =========================================================================
FROM composer:2 AS composer


# =========================================================================
# Stage 3: Image aplikasi (PHP-FPM + nginx + yt-dlp + ffmpeg)
# =========================================================================
# PHP 8.4: composer.lock mengunci paket Symfony yang butuh php >=8.4.1,
# walau composer.json menulis ^8.3.
FROM php:8.4-fpm-bookworm AS app

# --- Paket sistem: nginx, supervisor, ffmpeg, python3 (untuk yt-dlp), tools ---
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        ffmpeg \
        python3 \
        ca-certificates \
        curl \
        unzip \
        git \
    && rm -rf /var/lib/apt/lists/*

# --- Ekstensi PHP yang dibutuhkan Laravel ---
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
        pdo_sqlite \
        mbstring \
        bcmath \
        zip \
        intl \
        pcntl \
        opcache

# --- yt-dlp (binary generik, jalan di atas python3, portable amd64/arm64) ---
ADD --chmod=0755 https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp /usr/local/bin/yt-dlp

# --- Composer dari stage sebelumnya ---
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

# --- Konfigurasi PHP, nginx, supervisor ---
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

# --- Install dependency PHP (tanpa dev, autoloader optimal) ---
# Copy file composer dulu untuk cache, install tanpa script
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist

# --- Copy seluruh aplikasi ---
COPY . .

# --- Ambil hasil build aset dari stage assets ---
COPY --from=assets /app/public/build ./public/build

# --- Siapkan .env & selesaikan autoloader/discovery ---
RUN cp -n .env.example .env \
    && composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi

# --- Normalisasi entrypoint (jaga-jaga CRLF dari Windows) & buat executable ---
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh

# --- Kepemilikan awal untuk direktori tulisan ---
RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1/ >/dev/null || exit 1

ENTRYPOINT ["entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
