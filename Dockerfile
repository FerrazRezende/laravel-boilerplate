# Stages:
#   base       PHP 8.5 with every extension the app needs. Nothing else.
#   dev        what docker-compose.yml runs: Composer and Node on board, and
#              www-data aligned with the host user for the bind mount.
#   vendor     production Composer install (no dev packages).
#   assets     production Vite build.
#   production the deployable image: code, vendor and built assets only — no
#              Composer, no Node, no .env (see .dockerignore).
#
# `docker compose` builds `dev` (every service sets target: dev). A plain
# `docker build .` builds the last stage, which is `production`.

FROM php:8.5-fpm AS base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    postgresql-client \
    libpq-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    supervisor \
    libssl-dev \
    libcurl4-openssl-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions. OPcache is compiled into the php:8.5 image itself
# (no more modules/opcache.so to build), so it's dropped from this list —
# docker-php-ext-install now fails on it since there's nothing left to build.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    sockets

# The php base image ships a channel.xml snapshot that goes stale, which
# makes pecl fail with "No releases available for package" for anything.
# Refreshing it before installing is the standard fix.
RUN pecl channel-update pecl.php.net

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Swoole powers Octane. The -D flags answer pecl's interactive prompts, which
# would otherwise hang the build. curl and openssl are on because Octane's
# concurrent task helpers and TLS-bound clients need them.
RUN pecl install -D 'enable-openssl="yes" enable-swoole-curl="yes" enable-sockets="yes"' swoole \
    && docker-php-ext-enable swoole

WORKDIR /var/www/html

EXPOSE 8000

# ---------------------------------------------------------------------------

FROM base AS dev

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install Node.js & NPM
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Align www-data with the host user. The project is bind-mounted over
# /var/www/html in development, which shadows any ownership baked into the
# image, so the container user must match the host UID/GID to be able to write
# to storage/ and bootstrap/cache.
ARG WWW_UID=1000
ARG WWW_GID=1000
RUN groupmod -o -g ${WWW_GID} www-data \
    && usermod -o -u ${WWW_UID} -g ${WWW_GID} www-data

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]

# ---------------------------------------------------------------------------

FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . /var/www/html

# --no-scripts: package discovery needs the app booted, which happens at
# runtime instead (Laravel rebuilds bootstrap/cache/packages.php on first boot).
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ---------------------------------------------------------------------------

# resources/js imports Ziggy straight out of vendor/, so the build needs the
# Composer stage's tree, not just the JavaScript sources.
FROM node:20-bookworm-slim AS assets

WORKDIR /var/www/html

COPY --from=vendor /var/www/html /var/www/html

RUN npm ci --no-audit --no-fund && npm run build

# ---------------------------------------------------------------------------

FROM base AS production

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

COPY --from=vendor --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /var/www/html/public/build /var/www/html/public/build
COPY --from=assets --chown=www-data:www-data /var/www/html/bootstrap/ssr /var/www/html/bootstrap/ssr

RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]
