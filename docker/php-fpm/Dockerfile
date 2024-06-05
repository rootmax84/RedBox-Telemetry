FROM phpdockerio/php:8.3-fpm
WORKDIR "/data"

RUN apt-get update \
    && apt-get -y --no-install-recommends install \
        php8.3-gd \
        php8.3-mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*
