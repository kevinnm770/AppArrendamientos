FROM php:8.4-fpm

# Dependencias del sistema necesarias para las extensiones de PHP y para Laravel
RUN apt-get update && apt-get install -y \
        git \
        curl \
        unzip \
        zip \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
        libzip-dev \
        libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Extensiones de PHP requeridas por Laravel y los paquetes del proyecto
# (pdo_mysql: MySQL, gd: manejo de imágenes, intl: localización es/en,
#  zip/exif: adjuntos y getid3, bcmath/opcache: rendimiento)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# El código fuente se monta como volumen (ver docker-compose.yml), por lo que
# no se copia aquí. php-fpm corre como root en el contenedor y delega cada
# request al usuario definido en el pool (www.conf, por defecto www-data).

EXPOSE 9000

CMD ["php-fpm"]
