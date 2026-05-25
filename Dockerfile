FROM composer:2 AS composer

FROM php:8.4-cli

WORKDIR /app

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip zip \
    && rm -rf /var/lib/apt/lists/*

CMD ["php", "-v"]
