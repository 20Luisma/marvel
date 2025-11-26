FROM php:8.2-fpm

# 1) Instalar dependencias del sistema y extensiones PHP necesarias
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
 && docker-php-ext-configure zip \
 && docker-php-ext-install pdo pdo_mysql zip \
 && rm -rf /var/lib/apt/lists/*

# 2) Instalar Composer desde la imagen oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3) Directorio de trabajo dentro del contenedor
WORKDIR /var/www/openai-service

# 4) Copiar solo composer.* primero para aprovechar la cache de Docker
COPY composer.json composer.lock* ./

# 5) Instalar dependencias de Composer en modo producción
RUN composer install --no-interaction --prefer-dist --no-dev

# 6) Copiar el resto del código del microservicio
COPY . .

# 7) Exponer el puerto interno donde correrá el servidor embebido de PHP
EXPOSE 8081

# 8) Comando por defecto: servidor PHP embebido sirviendo desde public/
CMD ["php", "-S", "0.0.0.0:8081", "-t", "public"]
