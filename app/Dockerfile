FROM php:8.2-apache

# 1. Instalar dependências do sistema
RUN apt-get update && apt-get install -y --no-install-recommends \
    libldap2-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# 2. Configurar e instalar extensões PHP
RUN docker-php-ext-configure ldap \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) ldap mysqli pdo pdo_mysql gd zip \
    && docker-php-ext-enable ldap mysqli pdo pdo_mysql gd zip

# 3. Instalar o Composer (MÉTODO CORRIGIDO E OFICIAL)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# 4. Definir o diretório de trabalho
WORKDIR /var/www/html