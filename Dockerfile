FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev libonig-dev \
    && docker-php-ext-install pdo_sqlite mbstring \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV BLUEVERA_STORAGE=/data

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf

COPY . /var/www/html
COPY docker-entrypoint-bluevera.sh /usr/local/bin/docker-entrypoint-bluevera

RUN chmod +x /usr/local/bin/docker-entrypoint-bluevera \
    && mkdir -p /data /var/www/html/public/uploads \
    && chown -R www-data:www-data /var/www/html /data

ENTRYPOINT ["docker-entrypoint-bluevera"]
CMD ["apache2-foreground"]
