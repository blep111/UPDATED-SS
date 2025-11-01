FROM php:8.2-apache

RUN a2enmod rewrite

COPY . /var/www/html/

WORKDIR /var/www/html

RUN chmod -R 755 /var/www/html

EXPOSE 80
