FROM phpdockerio/php:8.4-fpm

RUN apt-get update \
    && apt-get -y --no-install-recommends install \
        php8.4-mysql \
        php8.4-memcached \
        nginx \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

EXPOSE 80

RUN echo '#!/bin/sh' > /start.sh && \
    echo 'echo "======================"' >> /start.sh && \
    echo 'echo "== RedBox Telemetry =="' >> /start.sh && \
    echo 'echo "======================"' >> /start.sh && \
    echo 'touch /var/www/html/install' >> /start.sh && \
    echo 'php-fpm8.4 -O' >> /start.sh && \
    echo 'nginx -g "daemon off;"' >> /start.sh && \
    chmod +x /start.sh

CMD ["/start.sh"]