FROM php:8.4-apache

# Установка временной зоны
RUN apt-get update && apt-get install -y tzdata
ENV TZ=Europe/Moscow
RUN echo "date.timezone = Europe/Moscow" > /usr/local/etc/php/conf.d/timezone.ini

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libpq-dev
RUN docker-php-ext-install pdo_mysql mysqli mbstring exif pcntl bcmath gd zip \
     pdo_pgsql pgsql
RUN apt-get update && apt-get install -y curl unzip && \
    curl -sS https://getcomposer.org/installer -o composer-setup.php \
     && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
     && rm composer-setup.php