ARG PHP_VERSION=8.4

FROM php:${PHP_VERSION}-alpine AS base-env

WORKDIR /tmp
RUN apk add --no-cache \
    libstdc++ nodejs npm \
    #  awscli \
    && curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip" \
    && unzip awscliv2.zip \
    && ./aws/install \
    && rm awscliv2.zip \
    # composer \
    && wget https://getcomposer.org/installer \
    && php ./installer && rm installer \
    && mv composer.phar /usr/local/bin/composer

# ----------------
# PHP Extensions
# ----------------
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions intl pdo_pgsql

COPY ./run-docker.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/run-docker.sh

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

WORKDIR /app/php

COPY php/composer.json php/composer.lock ./

RUN composer install --no-scripts --no-dev --no-autoloader

###> recipes ###
###< recipes ###

EXPOSE 8000

CMD ["/usr/local/bin/run-docker.sh"]
