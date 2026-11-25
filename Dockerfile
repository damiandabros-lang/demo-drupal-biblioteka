# DEMO Isvero Studio – serwis biblioteki na Drupal 11 (PHP 8.3 + Apache).
FROM composer:2 AS composer

FROM php:8.3-apache-bookworm
RUN set -eux; \
  apt-get update; \
  apt-get install -y --no-install-recommends git unzip libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev libicu-dev; \
  docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
  docker-php-ext-install -j"$(nproc)" gd opcache pdo_mysql zip intl; \
  apt-get purge -y --auto-remove libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev libicu-dev; \
  apt-get install -y --no-install-recommends libfreetype6 libjpeg62-turbo libpng16-16 libwebp7 libzip4 libicu72; \
  rm -rf /var/lib/apt/lists/*; \
  a2enmod rewrite headers expires; \
  { echo 'opcache.memory_consumption=96'; echo 'opcache.max_accelerated_files=10000'; echo 'opcache.revalidate_freq=60'; } > /usr/local/etc/php/conf.d/opcache-demo.ini; \
  { echo 'memory_limit=192M'; echo 'expose_php=Off'; echo 'upload_max_filesize=8M'; echo 'post_max_size=8M'; } > /usr/local/etc/php/conf.d/demo.ini
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /opt/drupal
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --no-progress --optimize-autoloader
COPY web/modules/custom web/modules/custom
COPY web/themes/custom web/themes/custom
COPY scripts scripts
COPY docker/settings.php web/sites/default/settings.php
COPY docker/entrypoint.sh /usr/local/bin/demo-entrypoint
RUN mkdir -p config/sync web/sites/default/files && chown -R www-data:www-data web/sites/default/files

EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=180s CMD php -r 'exit(@file_get_contents("http://127.0.0.1/start") === false ? 1 : 0);'
CMD ["demo-entrypoint"]
