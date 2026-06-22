FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libonig-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring \
    && a2enmod rewrite \
    && echo 'variables_order = "EGPCS"' > /usr/local/etc/php/conf.d/env.ini \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

COPY . .

EXPOSE 80