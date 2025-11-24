FROM shinsenter/php:8.5-fpm-nginx

# Prepare
RUN phpaddmod mysqli memcached
RUN echo '#!/bin/sh' > /startup/99-greeting && \
    echo 'echo "======================"' >> /startup/99-greeting && \
    echo 'echo "== RedBox Telemetry =="' >> /startup/99-greeting && \
    echo 'echo "======================"' >> /startup/99-greeting && \
    echo 'find /var/www/html -type d -exec chmod 755 {} \;' >> /startup/99-greeting && \
    echo 'find /var/www/html -type f -exec chmod 644 {} \;' >> /startup/99-greeting && \
    echo 'chown -R www-data:www-data /var/www/html' >> /startup/99-greeting && \
    echo 'if [ ! -f /var/www/html/creds.php ] && [ -f /var/www/html/creds.php.example ]; then' >> /startup/99-greeting && \
    echo '    cp /var/www/html/creds.php.example /var/www/html/creds.php' >> /startup/99-greeting && \
    echo '    chmod 644 /var/www/html/creds.php' >> /startup/99-greeting && \
    echo '    echo "creds.php created from example file"' >> /startup/99-greeting && \
    echo 'fi' >> /startup/99-greeting && \
    echo 'touch /var/www/html/install' >> /startup/99-greeting && \
    echo 'chmod 644 /var/www/html/install' >> /startup/99-greeting && \
    echo 'sed -i "s/worker_connections 65535;/worker_connections 1024;/g" /etc/nginx/nginx.conf' >> /startup/99-greeting && \
    chmod +x /startup/99-greeting
