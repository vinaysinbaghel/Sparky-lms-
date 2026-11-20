#!/bin/bash
set -e

echo "[Sparky LMS] Starting container entrypoint..."

# 1. Validate mandatory application signing key (SECURITY_KEY)
if [ -z "$SECURITY_KEY" ] || [ "${#SECURITY_KEY}" -lt 32 ]; then
    echo "[Sparky LMS] CRITICAL ERROR: SECURITY_KEY is missing or fewer than 32 characters." >&2
    echo "[Sparky LMS] A secure secret key is required for session signing and cryptographic tokens." >&2
    echo "[Sparky LMS] Generate a key using: openssl rand -hex 32" >&2
    echo "[Sparky LMS] Set SECURITY_KEY in your .env file or container environment." >&2
    exit 1
fi

# 2. Verify PHP dependencies and autoload.php
if [ ! -f "/var/www/sparky-lms/vendor/autoload.php" ]; then
    echo "[Sparky LMS] vendor/autoload.php not found. Installing Composer dependencies..."
    if ! composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader; then
        echo "[Sparky LMS] CRITICAL ERROR: composer install failed during container startup." >&2
        exit 1
    fi
fi

# 3. Ensure essential runtime writable directories exist
for dir in /var/www/sparky-lms/app/cache /var/www/sparky-lms/app/logs /var/www/sparky-lms/app/upload /var/www/sparky-lms/app/courses; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
    fi
done

# 4. Set least-privilege permissions: only mutable directories are writable by PHP-FPM (www-data)
# Application source code remains read-only to PHP-FPM (owned by root:root)
chown -R www-data:www-data /var/www/sparky-lms/app/cache /var/www/sparky-lms/app/logs /var/www/sparky-lms/app/upload /var/www/sparky-lms/app/courses 2>/dev/null || true
chmod -R 775 /var/www/sparky-lms/app/cache /var/www/sparky-lms/app/logs /var/www/sparky-lms/app/upload /var/www/sparky-lms/app/courses 2>/dev/null || true

echo "[Sparky LMS] Environment initialized. Executing: $@"
exec "$@"
