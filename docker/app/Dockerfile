FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y libpq-dev git unzip libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql bcmath gd

COPY ./docker/app/000-default.conf /etc/apache2/sites-available/000-default.conf

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY ./src/ /var/www/

WORKDIR "/var/www/"

RUN composer install

RUN chown -R www-data:www-data ./app/storage

RUN mkdir -p ./storage/upload \
    && chown -R www-data:www-data ./storage/upload \
    && chmod -R 755 ./storage/upload

RUN a2enmod rewrite

RUN service apache2 restart
