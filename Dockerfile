# Simple Dockerfile for the LAN Chat application
# Based on the official php-apache image with MySQL extensions

FROM php:8.0-apache

# enable rewrite module (used by .htaccess if present)
RUN a2enmod rewrite

# install necessary PHP extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# copy the application into the container
# use a bind mount in Compose for development instead of copying
COPY . /var/www/html/

# set working directory
WORKDIR /var/www/html

# ensure uploads directory is writable
RUN chown -R www-data:www-data uploads/ && chmod -R 755 uploads/

# expose port 80
EXPOSE 80

# default command is apache2-foreground from the base image
