FROM node:22-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.4-fpm-alpine AS runtime
RUN apk add --no-cache libpq icu-libs libzip oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev icu-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install pdo_pgsql intl zip mbstring bcmath opcache pcntl \
    && apk del .build-deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache
COPY --from=frontend /app/public/build ./public/build
COPY deploy/php.ini /usr/local/etc/php/conf.d/brudel.ini
USER www-data
CMD ["php-fpm"]

FROM nginx:stable-alpine AS web
COPY deploy/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=runtime /var/www/html/public /var/www/html/public
