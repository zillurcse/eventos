# ── EventOS API — PHP 8.3 (FPM) ───────────────────────────────────
FROM php:8.3-fpm-bookworm

# System libraries needed to build PHP extensions + psql client (wait-for-db)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip \
        libzip-dev libpq-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libicu-dev libonig-dev \
        postgresql-client \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions required by the stack
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql gd intl bcmath zip pcntl opcache exif sockets

# Redis extension (cache / queue / Reverb scaling)
RUN pecl install redis && docker-php-ext-enable redis

# Composer (from the official image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP ini tweaks for development
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-eventos.ini

# Entrypoint: wait-for-db → composer install (api only) → migrate (api only)
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
