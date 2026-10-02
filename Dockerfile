FROM php:8.2-apache

# Install system deps + PHP extensions
RUN apt-get update && apt-get install -y \
        libpng-dev \
        libjpeg-dev \
        libwebp-dev \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable required Apache modules
RUN a2enmod rewrite headers deflate expires

# Allow .htaccess in the document root
RUN echo '<Directory /var/www/html>\n\tAllowOverride All\n\tOptions -Indexes\n</Directory>' \
    >> /etc/apache2/apache2.conf

# Copy application source
COPY . /var/www/html/

# Remove any local XAMPP artefacts that shouldn't be in the image
RUN rm -f /var/www/html/.env \
           /var/www/html/.env.local \
           /var/www/html/SETUP_NEW_LAPTOP.txt \
           /var/www/html/GUIDE_OFFLINE.txt \
           /var/www/html/GUIDE_ONLINE.txt \
           /var/www/html/Plans.txt 2>/dev/null; true

# Correct ownership for Apache
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

EXPOSE 80

CMD ["apache2-foreground"]
