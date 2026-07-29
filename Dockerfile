FROM php:8.2-apache

# Instalacija PDO MySQL ekstenzije
RUN docker-php-ext-install pdo pdo_mysql

# Omogućavanje Apache rewrite modula po potrebi
RUN a2enmod rewrite

# Postavljanje radnog direktorijuma
WORKDIR /var/www/html
