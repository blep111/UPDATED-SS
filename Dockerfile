FROM php:8.2-apache

RUN docker-php-ext-install curl

# Enable Apache rewrite module (optional but safer)
RUN a2enmod rewrite

# Copy app files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Permissions fix for Render
RUN chmod -R 755 /var/www/html

EXPOSE 80
