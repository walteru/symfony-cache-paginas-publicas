FROM php:8.3-apache

# Dependencias del sistema y extensiones PHP que necesita Symfony + Doctrine (SQLite).
# $PHPIZE_DEPS (compilador, autoconf…) hace falta para compilar la extensión redis
# vía PECL, que es la que usa el transport de Messenger sobre Redis.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libsqlite3-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_sqlite \
        intl \
        zip \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Composer (copiado desde la imagen oficial)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: habilitar rewrite y apuntar el docroot a public/ (convención Symfony)
RUN a2enmod rewrite
COPY vhost.conf /etc/apache2/sites-available/000-default.conf

# Entrypoint: ajusta permisos de var/ y arranca Apache
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint.sh"]
