# Production image: Apache + PHP 8.5, queue worker and scheduler included.
# Frontend assets are compiled inside the image because the Vite build
# shells out to `php artisan wayfinder:generate`.
FROM php:8.5-apache

RUN sed -i 's|http://deb\.debian\.org|http://ftp.fr.debian.org|g; s|https://deb\.debian\.org|http://ftp.fr.debian.org|g' /etc/apt/sources.list /etc/apt/sources.list.d/*.list /etc/apt/sources.list.d/*.sources 2>/dev/null; \
    apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        libsqlite3-dev \
        libzip-dev \
        supervisor \
        unzip \
    && docker-php-ext-install \
        bcmath \
        pcntl \
        pdo_sqlite \
        zip \
    && a2enmod rewrite headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && rm -rf /var/lib/apt/lists/*

# Node 22 for the one-time asset build (purged afterwards).
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html/laravel

# PHP tuning: upload limits for video uploads + production OPcache.
COPY docker/php-uploads.ini /usr/local/etc/php/conf.d/99-hxscreen.ini
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/hxscreen.conf
COPY docker/entrypoint.sh /usr/local/bin/hxscreen-entrypoint.sh
RUN chmod +x /usr/local/bin/hxscreen-entrypoint.sh

COPY laravel/composer.json laravel/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader --no-scripts

COPY laravel/package.json laravel/package-lock.json ./
RUN npm ci
COPY laravel/ ./
RUN php artisan package:discover --ansi --no-interaction \
    && npm run build \
    && npm cache clean --force \
    && apt-get purge -y nodejs \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/* /root/.npm

RUN mkdir -p storage/app/public database \
    && touch database/database.sqlite \
    && chown -R www-data:www-data storage bootstrap/cache database

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD curl -fsS http://localhost/up || exit 1

ENTRYPOINT ["hxscreen-entrypoint.sh"]
