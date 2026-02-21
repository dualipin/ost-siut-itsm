FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql gd

# ESTA LÍNEA ES LA CLAVE
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite
