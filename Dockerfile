# Use PHP 8.1 with Apache
FROM php:8.1-apache

# Install system dependencies and PHP extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev zip unzip \
        libpng-dev libjpeg-dev libfreetype6-dev \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql gd zip opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Set Apache document root to public/
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/000-default.conf \
    && sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf

# Copy application source code
COPY . /var/www/html

# Ensure permissions are correct
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose HTTP port
EXPOSE 80

# Use a simple entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
