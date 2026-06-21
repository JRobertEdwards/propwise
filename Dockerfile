FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY vite.config.js ./
COPY resources/ ./resources/
RUN npm run build

FROM composer:2 AS php-deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --optimize-autoloader \
    --prefer-dist

FROM php:8.3-fpm-alpine3.21
WORKDIR /var/www/html

RUN apk add --no-cache postgresql-dev libzip-dev \
    && apk add --no-cache --virtual .build-deps autoconf g++ make \
    && docker-php-ext-install -j$(nproc) pdo_pgsql zip pcntl \
    && docker-php-ext-enable opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/pear

RUN apk add --no-cache python3 py3-pip proj \
    && apk add --no-cache --virtual .py-build-deps build-base python3-dev proj-dev \
    && python3 -m venv /opt/etl-venv \
    && /opt/etl-venv/bin/pip install --no-cache-dir \
        pandas==2.2.3 psycopg2==2.9.10 python-dotenv==1.0.1 pyproj==3.7.1 tqdm==4.67.1 \
    && apk del .py-build-deps

ENV PATH="/opt/etl-venv/bin:$PATH"

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY --from=php-deps /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
