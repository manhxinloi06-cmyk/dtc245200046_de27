FROM php:8.3-apache
RUN docker-php-ext-install pdo pdo_mysql     && a2enmod rewrite headers     && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf     && sed -ri 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf \
    && chown -R www-data:www-data /var/www/html
COPY --chown=www-data:www-data . /var/www/html
USER www-data
EXPOSE 8080
