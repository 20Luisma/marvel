FROM php:8.5.3RC1-apache

# Activar mod_rewrite
RUN a2enmod rewrite

# Instalar extensiones PHP necesarias
RUN docker-php-ext-install pdo pdo_mysql

# Configurar DocumentRoot (ajustado si existe carpeta /public)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf

WORKDIR /var/www/html

# Copiar composer primero para cache de capas
COPY composer.json composer.lock ./

# Instalar Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && rm composer-setup.php

# Instalar dependencias
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copiar resto del código
COPY . .

# Permisos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
