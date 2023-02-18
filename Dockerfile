FROM php:7.4-apache
LABEL maintainer="Ralph Pavenstaedt<ralph@pavenstaedt.com>"

# Update and install necessary packages
RUN apt-get update && apt-get install libpng-dev libpq-dev libzip-dev zip xpdf vim -y
#RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql
RUN docker-php-ext-install gd mysqli pdo pdo_mysql zip
RUN pear channel-update pear.php.net
RUN pear install Log
RUN pecl install zip
RUN apt update && \
    apt install -y libmagickwand-dev --no-install-recommends && \
    pecl install imagick && docker-php-ext-enable imagick && \
    rm -rf /var/lib/apt/lists/*

# Get seeddms
COPY seeddms-quickstart-6.0.22/seeddms60x /var/www/seeddms
RUN touch /var/www/seeddms/conf/ENABLE_INSTALL_TOOL

# Copy settings-files
COPY sources/php.ini /usr/local/etc/php/
COPY sources/000-default.conf /etc/apache2/sites-available/
COPY sources/settings.xml /var/www/seeddms/conf/settings.xml
RUN chown -R www-data:www-data /var/www/seeddms/

RUN a2enmod rewrite

# Volumes to mount
VOLUME [ "/var/www/seeddms/data", "/var/www/seeddms/conf", "/var/www/seeddms/www/ext" ]