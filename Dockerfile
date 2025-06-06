# Use an official PHP image with Apache
FROM php:8.2-apache

# Set the working directory inside the container
WORKDIR /var/www/html

# Install Composer dependencies
# Copy composer.json and composer.lock first to leverage Docker cache
COPY composer.json composer.lock ./

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy the rest of your application files
COPY . .

# Expose port 80 for the web server
EXPOSE 80

# The base image's default command will start Apache, serving your PHP application.
# No CMD instruction is needed here.

