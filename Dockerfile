FROM php:8.1-apache

LABEL maintainer="me@monlor.com"
LABEL version="2.1.0"

ENV TZ=Asia/Shanghai

RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libgmp-dev \
    libcurl4-openssl-dev \
    libzip-dev \
    libonig-dev \
    openssl \
    rsync \
    git \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        mysqli \
        pdo \
        pdo_mysql \
        bcmath \
        gmp \
        curl \
        mbstring \
        zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite

COPY . /var/www/html/

RUN git clone --depth 1 https://github.com/LightCountry/TokenPay /tmp/tokenpay \
    && cat /tmp/tokenpay/Plugs/epay/*.sql > /var/www/html/epay.sql \
    && cp -a /tmp/tokenpay/Plugs/epay/. /var/www/html/ \
    && rm -rf /tmp/tokenpay

RUN cp -r /var/www/html/docker-plugins/* /var/www/html/plugins/ \
    && cp -r /var/www/html/docker-assets/* /var/www/html/assets/ \
    && rm -rf /var/www/html/docker-plugins /var/www/html/docker-assets

RUN chmod -R 777 /var/www/html

COPY start.sh /opt/start.sh
RUN chmod +x /opt/start.sh

EXPOSE 80

CMD ["/opt/start.sh"]
