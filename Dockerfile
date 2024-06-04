FROM nibrev/php-5.3-apache

# Installs curl
RUN docker-php-ext-install curl