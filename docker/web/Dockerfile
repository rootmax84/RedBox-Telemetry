FROM phpdockerio/php:8.4-fpm

RUN apt-get update \
    && apt-get -y --no-install-recommends install \
        php8.4-mysql \
        php8.4-memcached \
        nginx \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

EXPOSE 80

RUN sed -i 's/listen = \[::\]:9000/listen = \/var\/run\/php\/php-fpm.sock/g' /etc/php/8.4/fpm/pool.d/z-overrides.conf

RUN echo '#!/bin/sh' > /start.sh && \
    echo 'echo "======================"' >> /start.sh && \
    echo 'echo "== RedBox Telemetry =="' >> /start.sh && \
    echo 'echo "======================"' >> /start.sh && \
    echo 'find /var/www/html -type d -exec chmod 755 {} \;' >> /start.sh && \
    echo 'find /var/www/html -type f -exec chmod 644 {} \;' >> /start.sh && \
    echo 'chown -R www-data:www-data /var/www/html' >> /start.sh && \
    echo 'if [ ! -f /var/www/html/creds.php ] && [ -f /var/www/html/creds.php.example ]; then' >> /start.sh && \
    echo '    cp /var/www/html/creds.php.example /var/www/html/creds.php' >> /start.sh && \
    echo '    chmod 644 /var/www/html/creds.php' >> /start.sh && \
    echo '    echo "creds.php created from example file"' >> /start.sh && \
    echo 'fi' >> /start.sh && \
    echo 'touch /var/www/html/install' >> /start.sh && \
    echo 'chmod 644 /var/www/html/install' >> /start.sh && \
    echo 'php-fpm8.4 -D' >> /start.sh && \
    echo 'nginx -g "daemon off;"' >> /start.sh && \
    chmod +x /start.sh

STOPSIGNAL SIGKILL

CMD ["/start.sh"]