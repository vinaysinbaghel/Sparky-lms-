#!/bin/bash
set -e

echo "[Sparky LMS] Starting container entrypoint..."

# If vendor/autoload.php is missing (e.g. host volume mounted over /var/www/sparky-lms),
# automatically install production dependencies via Composer.
if [ ! -f "/var/www/sparky-lms/vendor/autoload.php" ]; then
    echo "[Sparky LMS] vendor/autoload.php not found. Installing Composer dependencies..."
    composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --ignore-platform-reqs
fi

# Ensure essential runtime writable directories exist
for dir in /var/www/sparky-lms/app/cache /var/www/sparky-lms/app/logs /var/www/sparky-lms/app/upload /var/www/sparky-lms/app/courses; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
    fi
done

# Set permissions for PHP-FPM worker process (www-data)
chown -R www-data:www-data /var/www/sparky-lms/app/cache /var/www/sparky-lms/app/logs /var/www/sparky-lms/app/upload /var/www/sparky-lms/app/courses 2>/dev/null || true
chmod -R 775 /var/www/sparky-lms/app/cache /var/www/sparky-lms/app/logs /var/www/sparky-lms/app/upload /var/www/sparky-lms/app/courses 2>/dev/null || true

echo "[Sparky LMS] Environment initialized. Executing: $@"
exec "$@"
