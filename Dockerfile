FROM php:8.4-apache

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev
RUN docker-php-ext-install pdo_mysql mysqli mbstring exif pcntl bcmath gd zip
RUN apt-get update && apt-get install -y curl unzip && \
    curl -sS https://getcomposer.org/installer -o composer-setup.php \
     && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
     && rm composer-setup.php