# FixFlow app — self-contained runtime image (frontend prebuilt, sqlite storage)
# Stage 1: build the Vue/Vite frontend
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

# Stage 2: PHP runtime
FROM php:8.4-cli-alpine
RUN apk add --no-cache icu libzip sqlite zip unzip curl \
    && apk add --no-cache --virtual .build-deps icu-dev libzip-dev sqlite-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_sqlite bcmath zip intl pcntl \
    && apk del .build-deps
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN COMPOSER_NO_AUDIT=1 composer install --no-interaction --no-scripts --prefer-dist --optimize-autoloader
COPY . .
COPY --from=frontend /app/public/build ./public/build
COPY --from=frontend /app/node_modules ./node_modules
COPY docker/entrypoint.sh /app/docker/entrypoint.sh
RUN sed -i 's/\r$//' /app/docker/entrypoint.sh && chmod +x /app/docker/entrypoint.sh

ENV APP_ENV=local \
    APP_DEBUG=true \
    APP_URL=http://localhost:8790 \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/app/database/database.sqlite \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=database \
    LOG_CHANNEL=stderr \
    PHP_CLI_SERVER_WORKERS=4

EXPOSE 80
ENTRYPOINT ["sh", "/app/docker/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=80"]
