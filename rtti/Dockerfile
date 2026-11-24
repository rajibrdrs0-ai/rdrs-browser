# RTTI – Training Institute Management System (PHP 8.2 + Apache)
FROM php:8.2-apache

RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql zip gd \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini /usr/local/etc/php/conf.d/rtti.ini
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/rtti-entrypoint
COPY . /var/www/rtti

RUN chmod +x /usr/local/bin/rtti-entrypoint \
 && rm -f /var/www/rtti/config/config.php /var/www/rtti/storage/installed.lock \
 && mkdir -p /var/www/rtti/storage/uploads /var/www/rtti/storage/logs /var/www/rtti/storage/backups \
 && chown -R www-data:www-data /var/www/rtti/config /var/www/rtti/storage /var/www/rtti/public/assets/img

WORKDIR /var/www/rtti
EXPOSE 80
ENTRYPOINT ["rtti-entrypoint"]
CMD ["apache2-foreground"]
