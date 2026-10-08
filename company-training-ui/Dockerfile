FROM php:8.3-apache

WORKDIR /var/www/html
COPY . /var/www/html/

# Serve the public directory only, keeping application PHP files private.
RUN sed -i 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' /etc/apache2/sites-available/000-default.conf \
    && a2enmod rewrite

EXPOSE 8080

# Railway provides PORT at runtime. Use 8080 locally when it is unset.
CMD ["sh", "-c", "sed -i \"s/^Listen 80$/Listen ${PORT:-8080}/\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \\*:80>/<VirtualHost *:${PORT:-8080}>/\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
