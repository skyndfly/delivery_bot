FROM php:8.4-apache

RUN apt-get update && apt-get install -y curl unzip && \
    curl -sS https://getcomposer.org/installer -o composer-setup.php \
     && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
     && rm composer-setup.php