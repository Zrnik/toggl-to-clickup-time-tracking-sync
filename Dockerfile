FROM php:8.3-fpm
RUN apt -y update && apt -y install git unzip
COPY --from=composer/composer:2-bin /composer /usr/bin/composer
