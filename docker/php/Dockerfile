# Image de base : PHP 8.4 avec FPM (le moteur qui exécute PHP)
FROM php:8.4-fpm

# 1. Installer les librairies système nécessaires aux extensions PHP
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/*

# 2. Installer les extensions PHP dont Symfony/Doctrine ont besoin
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql intl zip gd opcache

# 3. Installer Composer (récupéré depuis l'image officielle Composer)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Se placer dans le dossier où sera monté ton code
WORKDIR /var/www