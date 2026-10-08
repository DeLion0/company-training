# Configure Apache with exactly one MPM
RUN find /etc/apache2/mods-enabled/ \
    -maxdepth 1 -type l -name 'mpm_*' -delete \
    && a2enmod mpm_prefork rewrite \
    && apache2ctl -t

# Serve application through public directory
RUN sed -i \
    's#DocumentRoot /var/www/html#DocumentRoot /var/www/html/public#' \
    /etc/apache2/sites-available/000-default.conf

