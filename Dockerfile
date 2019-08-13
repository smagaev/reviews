FROM php:7.2-fpm
RUN apt-get update \
 && apt-get install -y git zlib1g-dev \
 && docker-php-ext-install pdo pdo_mysql zip
COPY . /usr/src/myapp
WORKDIR /usr/src/myapp/src/public
CMD php -S 0.0.0.0:80
EXPOSE 80
