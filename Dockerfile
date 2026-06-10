FROM php:7.3-apache

LABEL MAINTAINER me@monlor.com
LABEL VERSION 2.0.1

ENV TZ Asia/Shanghai

RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libmcrypt-dev \
    libgdiplus \
    openssl \
    rsync \
    git && \
    printf '\n' | pecl install mcrypt && \
    docker-php-ext-enable mcrypt && \
    docker-php-ext-configure gd \
     --with-freetype-dir=/usr/include/freetype2 \
     --with-png-dir=/usr/include \
     --with-jpeg-dir=/usr/include && \
    docker-php-ext-install gd mysqli pdo pdo_mysql

WORKDIR /var/www/html

COPY . /var/www/html/
RUN chmod -R 777 /var/www/html

RUN a2enmod rewrite

RUN git clone https://github.com/LightCountry/TokenPay && \
    cat TokenPay/Plugs/epay/*.sql > /var/www/html/epay.sql && \
    rm -rf TokenPay/Plugs/epay/*.sql TokenPay/Plugs/epay/{.git,README.md} && \
    cp -a TokenPay/Plugs/epay/* /var/www/html && \
    rm -rf TokenPay

RUN cp -r /var/www/html/docker-plugins/* /var/www/html/plugins/ && \
    cp -r /var/www/html/docker-assets/* /var/www/html/assets/ && \
    rm -rf /var/www/html/docker-plugins /var/www/html/docker-assets

COPY start.sh /opt/start.sh
RUN chmod +x /opt/start.sh

EXPOSE 80

CMD ["/opt/start.sh"]
