# ── Stage 1: Frontend build ───────────────────────────────────────────────────
FROM node:20-alpine AS frontend

# VITE_ vars must be available at build time — they are baked into the JS bundle.
ARG VITE_APP_NAME=Checkers
ARG VITE_REVERB_APP_KEY=checkers-app
ARG VITE_REVERB_HOST=localhost
ARG VITE_REVERB_SCHEME=https
ARG VITE_REVERB_PORT=443

WORKDIR /app
COPY package*.json ./
RUN npm ci --prefer-offline
COPY . .
RUN WAYFINDER_SKIP=1 npm run build

# ── Stage 2: PHP dependencies ─────────────────────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --ignore-platform-reqs \
    --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

# ── Stage 3: Production image ──────────────────────────────────────────────────
FROM dunglas/frankenphp:php8.3-alpine

# PHP extensions
RUN install-php-extensions pdo_pgsql pgsql pcntl zip opcache

# supervisord
RUN apk add --no-cache supervisor

WORKDIR /var/www/html

# Application files
COPY --from=vendor /app .
COPY --from=frontend /app/public/build ./public/build

# Docker configs
COPY .docker/Caddyfile /etc/caddy/Caddyfile
COPY .docker/supervisord.conf /etc/supervisord.conf
COPY .docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY .docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh \
    && mkdir -p storage/framework/{sessions,views,cache} storage/logs bootstrap/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
