FROM php:8.3-apache

RUN docker-php-ext-install mysqli pdo pdo_mysql

# Hindari error "More than one MPM loaded"
RUN a2dismod mpm_event mpm_worker || true \
 && a2enmod mpm_prefork

COPY . /var/www/html/

# Apache listen di port yang diberikan Railway
CMD sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf \
 && sed -i "s/:80>/:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf \
 && apache2-foreground