FROM php:8.3-fpm-alpine

# Pakiety systemowe + biblioteki potrzebne dla typowego Laravel
RUN apk add --no-cache \
    bash curl git unzip \
    icu-dev oniguruma-dev libzip-dev zlib-dev \
    mariadb-client

# Rozszerzenia PHP (Laravel + MySQL + zip + intl)
RUN docker-php-ext-install \
    pdo pdo_mysql mbstring intl zip opcache

# Domyślne 128M nie wystarcza dla crm:sync (paczki po 1000 rekordów) —
# proces ginął fatalem OOM, zostawiając runy "running" i wiszące locki.
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/zz-memory.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
