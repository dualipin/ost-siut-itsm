FROM docker.io/php:8.3-apache

ARG HOST_UID=1000
ARG HOST_GID=1000

RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        intl \
        zip \
        pdo \
        pdo_mysql \
        gd

RUN a2enmod rewrite

COPY --from=docker.io/composer:latest /usr/bin/composer /usr/bin/composer

# Reasignar www-data al UID/GID del host
RUN groupmod -g ${HOST_GID} www-data 2>/dev/null || true && \
    usermod  -u ${HOST_UID} -g ${HOST_GID} www-data

# Cambiar Apache de puerto 80 → 8080 (puerto alto, no requiere root)
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf && \
    sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-enabled/000-default.conf

# Suprimir la advertencia de ServerName
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data

RUN mkdir -p /var/www/html && \
    chown -R www-data:www-data /var/www/html /var/log/apache2 /var/run/apache2

WORKDIR /var/www/html

EXPOSE 8080