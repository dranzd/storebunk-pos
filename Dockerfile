FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git unzip zip libzip-dev && \
    docker-php-ext-install zip && \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
RUN composer install --no-interaction --prefer-dist
CMD ["./vendor/bin/phpunit", "--testdox"]
