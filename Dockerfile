FROM php:8.4-fpm

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

# Install PHP extensions
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
    opcache \
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

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

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

WORKDIR /var/www/html

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]
