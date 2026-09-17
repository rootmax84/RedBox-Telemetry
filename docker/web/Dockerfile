FROM shinsenter/php:8.5-fpm-nginx

# Prepare
RUN phpaddmod mysqli memcached redis
RUN printf '%s\n' \
    '#!/bin/sh' \
    'echo "======================"' \
    'echo "== RedBox Telemetry =="' \
    'echo "======================"' \
    'if [ -w /var/www/html ]; then' \
    '    find /var/www/html -type d -exec chmod 755 {} \;' \
    '    find /var/www/html -type f -exec chmod 644 {} \;' \
    '    chown -R www-data:www-data /var/www/html' \
    '    if [ ! -f /var/www/html/src/creds.php ] && [ -f /var/www/html/src/creds.php.example ]; then' \
    '        cp /var/www/html/src/creds.php.example /var/www/html/src/creds.php' \
    '        chmod 644 /var/www/html/src/creds.php' \
    '        echo "creds.php created from example file"' \
    '    fi' \
    '    touch /var/www/html/install' \
    '    chmod 644 /var/www/html/install' \
    'else' \
    '    echo "[startup] /var/www/html is read-only, skipping permission setup"' \
    'fi' \
    'sed -i "s/worker_connections 65535;/worker_connections 1024;/g" /etc/nginx/nginx.conf' \
    > /startup/99-greeting && \
    chmod +x /startup/99-greeting
