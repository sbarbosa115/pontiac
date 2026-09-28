FROM php:8.4-fpm-alpine

# icu-data-full: Alpine ships English-only ICU data, which makes intl format Spanish dates and COP amounts in English.
# gd: resizes the images consultants upload for their landing pages (WebP), as the cPanel server does.
RUN apk add --no-cache icu-dev icu-data-full libzip-dev libpng-dev libjpeg-turbo-dev libwebp-dev linux-headers git unzip bash \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql zip opcache gd \
    && apk del --no-cache linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Run as the host user so files created in the bind mount (vendor/, migrations) aren't root-owned
ARG UID=1000
ARG GID=1000
RUN addgroup -g "${GID}" app && adduser -D -u "${UID}" -G app app \
    && mkdir -p /var/uploads && chown app:app /var/uploads

COPY php.ini /usr/local/etc/php/conf.d/app.ini

USER app
WORKDIR /app
