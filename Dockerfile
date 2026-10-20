# Deploy to any Docker host (Render, Railway, Fly.io...). Set DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS as environment variables.
FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_mysql && a2enmod headers
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80
