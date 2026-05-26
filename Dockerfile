FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.4-cli

WORKDIR /app

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=vendor /app/vendor /app/vendor
COPY bin ./bin
COPY docs ./docs
COPY src ./src
COPY tests ./tests
COPY composer.json composer.lock ecs.php phpstan.neon.dist phpunit.xml README.md ./

CMD ["composer", "cli"]
