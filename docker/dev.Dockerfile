FROM php:8.5-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libfreetype6-dev libjpeg62-turbo-dev libpng-dev libpq-dev libwebp-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd pdo_pgsql zip \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN echo 'memory_limit = 512M' > "$PHP_INI_DIR/conf.d/zz-dev.ini"

WORKDIR /workspace
