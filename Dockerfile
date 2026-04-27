FROM php:8.2-apache

# Install PDO MySQL driver (required for your Database.php class)
RUN docker-php-ext-install pdo pdo_mysql

# Enable the rewrite module (common for PHP apps on Apache)
RUN a2enmod rewrite

COPY . /var/www/html

# Ensure the webserver can write to your uploads folder
RUN chown -R www-data:www-data /var/www/html/public/uploads

EXPOSE 80