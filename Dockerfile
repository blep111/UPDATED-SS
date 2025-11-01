FROM php:8.2-apache

# Enable Apache rewrite module (optional but safe)
RUN a2enmod rewrite

# Copy all app files
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Permissions fix for Render
RUN chmod -R 755 /var/www/html

EXPOSE 80
