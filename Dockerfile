FROM node:latest AS node
FROM php:8.3-cli-bullseye

COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node /usr/local/bin/node /usr/local/bin/node
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

COPY . /var/www/html

RUN apt-get update && apt-get install -y \
    curl \
    zip \
    unzip \
    libpq-dev \
    libjpeg-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libfreetype6-dev \
    libmcrypt-dev \
    libssl-dev

RUN docker-php-ext-configure gd \
--with-freetype=/usr/include/ \
--with-jpeg=/usr/include/

RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    gd \
    bcmath \
    opcache \
    mbstring \
    intl \
    exif \
    pcntl 

RUN pecl install redis && docker-php-ext-enable redis

WORKDIR /var/www/html

COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

RUN composer install --no-dev --optimize-autoloader

RUN php artisan storage:link

RUN php artisan scribe:generate

RUN npm install && npm run build

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]