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

# NOTE: app env (APP_ENV, DB_*, SESSION/CACHE/QUEUE, APP_URL, LOG_CHANNEL) is
# intentionally NOT baked in here. Image-level ENV leaks into PHP $_SERVER,
# which phpdotenv reads before putenv/$_ENV — so it would shadow phpunit.xml's
# test overrides (APP_ENV=testing, DB_DATABASE=:memory:) and make the suite run
# against the live DB. The entrypoint writes these into .env at boot instead.

EXPOSE 80
ENTRYPOINT ["sh", "/app/docker/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=80"]
