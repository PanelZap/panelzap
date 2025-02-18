# Estágio base: Instalar PHP, Node.js e dependências
FROM php:8.3-cli

# Instalar dependências do sistema, Node.js e npm
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
    libssl-dev \
    gnupg2 \
    lsb-release \
    ca-certificates \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm@latest \
    && rm -rf /var/lib/apt/lists/*

# Configurar e instalar extensões PHP necessárias
RUN docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install -j$(nproc) \
    pdo pdo_mysql pdo_pgsql gd bcmath opcache mbstring intl exif pcntl \
    && pecl install redis && docker-php-ext-enable redis

# Copiar o Composer do contêiner oficial para o nosso contêiner
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Copiar arquivos do projeto para o contêiner
COPY . /var/www/html

WORKDIR /var/www/html

# Instalar dependências PHP via Composer (cacheável se não houver alterações em composer.json)
RUN composer install --no-dev --optimize-autoloader

# Criar link para armazenamento
RUN php artisan storage:link

# Gerar API docs (scribe)
RUN php artisan scribe:generate

# Expor a porta 8000 para o servidor PHP
EXPOSE 8000

# Rodar o servidor PHP
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]