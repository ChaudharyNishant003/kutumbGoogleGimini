FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql mysqli curl gd \
    && a2enmod rewrite headers deflate \
    && (a2dismod mpm_event || true) \
    && (a2dismod mpm_worker || true) \
    && (a2dismod mpm_prefork || true) \
    && a2enmod mpm_prefork \
    && rm -rf /var/lib/apt/lists/* \
    && echo "--- mods-enabled ---" && ls -la /etc/apache2/mods-enabled/ | grep -i mpm

RUN sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

COPY . /var/www/html/

# Keep a baked-in copy of the starting uploads so a persistent volume mounted
# at /var/www/html/uploads (empty on first attach) can be auto-seeded at
# container startup instead of losing the demo property photos.
RUN cp /var/www/html/config/config.railway.php /var/www/html/config/config.php \
    && mkdir -p /var/www/html/uploads/properties /var/www/html/uploads/documents \
    && cp -r /var/www/html/uploads /var/www/html/uploads-seed \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod +x /var/www/html/docker-start.sh

EXPOSE 80

CMD ["/var/www/html/docker-start.sh"]
