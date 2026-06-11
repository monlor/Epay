FROM php:8.1-apache

LABEL maintainer="me@monlor.com"
LABEL version="2.1.0"

ENV TZ=Asia/Shanghai

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

RUN apt-get update && apt-get install -y \
    openssl \
    rsync \
    supervisor \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions gd mysqli pdo pdo_mysql bcmath gmp curl mbstring zip

WORKDIR /var/www/html

COPY apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN a2enmod rewrite

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

COPY supervisord.conf /etc/supervisor/supervisord.conf
COPY supervisor/ /etc/supervisor/conf.d/

COPY start.sh /opt/start.sh
RUN chmod +x /opt/start.sh

EXPOSE 80

CMD ["/opt/start.sh"]
