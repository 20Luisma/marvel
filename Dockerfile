FROM php:8.2-apache

# 1) Instalar dependencias del sistema y extensiones PHP
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo pdo_mysql zip gd \
 && a2enmod rewrite \
 && rm -rf /var/lib/apt/lists/*

# 2) Configurar Apache DocumentRoot
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf

# 3) Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 4) Directorio de trabajo
WORKDIR /var/www/html

# 5) Copiar archivos de dependencias
COPY composer.json composer.lock* ./

# 6) Instalar dependencias (sin dev para producción)
RUN composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

# 7) Copiar el resto del código
COPY . .

# 8) Permisos para storage y carpetas de escritura
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage

EXPOSE 80

CMD ["apache2-foreground"]
