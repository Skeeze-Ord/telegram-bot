FROM php:8.2-fpm

# Устанавливаем необходимые пакеты и PDO MySQL
RUN apt-get update && apt-get install -y \
        libonig-dev \
        libzip-dev \
        unzip \
        default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html