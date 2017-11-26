FROM php:7.2-rc-cli
ARG COMPOSER_AUTH

ENV DEBIAN_FRONTEND noninteractive

#RUN apt-get update && \
#	apt-get install -y apt-transport-https build-essential curl libcurl3 libcurl4-openssl-dev libicu-dev zlib1g-dev libxml2-dev && \
#	docker-php-ext-install curl xml zip bcmath pcntl && \
#	apt-get clean

# Install Composer.
COPY --from=composer:1.5 /usr/bin/composer /usr/bin/composer

# Root App folder
RUN mkdir /app
WORKDIR /app
ADD . /app

# Install dependencies.
RUN composer install --no-dev --optimize-autoloader --no-progress --no-suggest --no-interaction

RUN ls -la

RUN rm -r /root/.composer/* /root/.composer
RUN ls -la /root

# Use to store the config inside a volume.
RUN mkdir /data && chmod 777 /data
VOLUME /data

VOLUME /mnt
WORKDIR /mnt

ENTRYPOINT ["php", "/app/bin/flickr-cli"]
