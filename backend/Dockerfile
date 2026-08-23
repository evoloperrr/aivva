FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
        git unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql bcmath \
    && pecl install redis && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

RUN php -r "file_exists('.env') || copy('.env.example', '.env');"

EXPOSE 10000

CMD php artisan config:clear \
    && php artisan migrate --force --seed \
    && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}
