# ── Stage 1: Build frontend assets ───────────────────────────────────────────
# Use Debian (glibc) for Vite/Rollup native bindings compatibility.
# Use Node LTS for best native-module support on Render.
FROM node:20-slim AS frontend
WORKDIR /app
RUN apt-get update \
    && apt-get install -y --no-install-recommends python3 make g++ php-cli unzip git curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Composer + PHP deps (needed for `php artisan wayfinder:generate` during Vite build)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# ── Stage 2: PHP + Nginx production image ────────────────────────────────────
FROM php:8.3-fpm-alpine

# System dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    postgresql-dev \
    icu-dev

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pdo_mysql \
        bcmath \
        gd \
        zip \
        pcntl \
        exif \
        mbstring \
        opcache \
        intl

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Install PHP dependencies (production only)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application
COPY . .
RUN composer run-script post-autoload-dump --no-interaction 2>/dev/null || true

# Copy built frontend assets
COPY --from=frontend /app/public/build ./public/build

# Set permissions
RUN mkdir -p storage/logs \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# OPcache config for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Copy config files
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 8080

CMD ["/start.sh"]
