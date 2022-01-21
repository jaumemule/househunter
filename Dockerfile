# ----------------------------------------------------------------
# Base layer
# ----------------------------------------------------------------

FROM php:8.0.10-fpm-alpine3.13

# Install the packages you need
RUN set -ex \
    && apk update \
    # Add system dependencies
    && echo "Add system dependencies" \
    && apk add --no-cache \
        alpine-sdk \
        libpng-dev \
        oniguruma-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        zip \
        jpegoptim optipng pngquant gifsicle \
        vim \
        unzip \
        git \
        curl \
        openssh-server \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    # Install PHP Extensions
    && echo "Install PHP Extensions" \
    && docker-php-ext-install pdo pdo_mysql mbstring zip exif pcntl gd sockets \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    # Configure user
    && addgroup -S www  \
    && adduser -S www -G www \
    # Create composer install directory
    && mkdir -p /var/www/.composer/cache/vcs \
    && chown -R www:www /var/www/.composer

# Set your working directory
WORKDIR /var/www

# ----------------------------------------------------------------
# Application layer
# ----------------------------------------------------------------

RUN chmod -R 777 /var/www # just because yes, or permissions complain in VPS

# Copy composer files
COPY composer.lock composer.json /var/www/

# Install composer dependencies
RUN sudo composer install --no-progress --no-plugins --no-scripts --no-dev --optimize-autoloader

# Copy existing application directory contents
COPY . /var/www

# Copy existing application directory permissions
RUN chown -R www:www /var/www

# Create an empty .env file to make symfony happy
RUN touch /var/www/.env

# Change current user to www
USER www
