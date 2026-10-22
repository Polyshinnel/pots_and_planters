# syntax=docker/dockerfile:1
FROM php:8.4-fpm-bookworm AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev libzip-dev libonig-dev libxml2-dev libfcgi-bin unzip \
    && docker-php-ext-install -j$(nproc) bcmath dom intl mbstring pdo_mysql pcntl zip opcache \
    && pecl install redis-6.3.0 && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY docker/backend/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/backend/fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf

FROM php-base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress
COPY backend/ ./
# package:discover boots Filament's view services, which need the compiled-view
# directory before Composer's post-autoload-dump scripts execute.
RUN mkdir -p bootstrap/cache storage/app/public storage/app/private \
    storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
RUN composer dump-autoload --no-dev --optimize --no-interaction
RUN php artisan filament:assets \
    && chown -R www-data:www-data storage bootstrap/cache

FROM dependencies AS development
RUN apt-get update && apt-get install -y --no-install-recommends libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/* \
    && composer install --no-interaction --prefer-dist --no-progress \
    && chown -R www-data:www-data /var/www/html
COPY docker/backend/development.ini /usr/local/etc/php/conf.d/zzz-development.ini
COPY docker/backend/entrypoint.sh /usr/local/bin/app-entrypoint
COPY docker/backend/healthcheck.sh /usr/local/bin/app-healthcheck
RUN chmod 755 /usr/local/bin/app-entrypoint /usr/local/bin/app-healthcheck
USER www-data
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm", "-F"]

FROM php-base AS runtime
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
COPY docker/backend/entrypoint.sh /usr/local/bin/app-entrypoint
COPY docker/backend/healthcheck.sh /usr/local/bin/app-healthcheck
RUN chmod 755 /usr/local/bin/app-entrypoint /usr/local/bin/app-healthcheck
USER www-data
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm", "-F"]
EXPOSE 9000

FROM nginx:1.28-alpine AS nginx
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=dependencies /var/www/html/public /var/www/html/public
