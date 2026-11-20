FROM php:8.3-fpm-alpine

# Install system dependencies & build tools
RUN apk add --no-cache \
    bash \
    curl \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    libxml2-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS

# Configure and install PHP extensions required by Chamilo / Sparky LMS
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        intl \
        zip \
        opcache \
        mbstring \
        xml \
        fileinfo \
        bcmath

# Install Redis PHP extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer from official multi-stage image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/sparky-lms

# Configure PHP settings for production
RUN { \
        echo 'upload_max_filesize = 512M'; \
        echo 'post_max_size = 512M'; \
        echo 'memory_limit = 512M'; \
        echo 'max_execution_time = 300'; \
        echo 'max_input_time = 300'; \
        echo 'display_errors = Off'; \
        echo 'display_startup_errors = Off'; \
        echo 'log_errors = On'; \
        echo 'expose_php = Off'; \
        echo 'session.use_strict_mode = 1'; \
        echo 'session.cookie_httponly = 1'; \
        echo 'session.use_only_cookies = 1'; \
    } > /usr/local/etc/php/conf.d/sparky-production.ini

# Copy composer definitions and install dependencies
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader

# Copy application source code
COPY . .

# Copy entrypoint script and configure runtime permissions
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data /var/www/sparky-lms \
    && chmod -R 755 /var/www/sparky-lms \
    && chmod -R 775 /var/www/sparky-lms/app/cache /var/www/sparky-lms/app/logs /var/www/sparky-lms/app/upload /var/www/sparky-lms/app/courses 2>/dev/null || true

# Expose PHP-FPM port
EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
