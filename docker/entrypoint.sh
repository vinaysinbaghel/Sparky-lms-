#!/bin/bash
# ==============================================================================
# Sparky LMS Ã¢â‚¬â€ Application Container Entrypoint (PHP-FPM)
# Validates mandatory secrets, dependency integrity, and runtime directories.
# ==============================================================================
set -e

echo "[Sparky LMS] Starting container entrypoint..."

# Helper function to check for insecure placeholders
is_placeholder() {
    local val="$1"
    local lower_val
    lower_val=$(echo "$val" | tr '[:upper:]' '[:lower:]')
    case "$lower_val" in
        *changeme*|*replace_with*|*password*|*testpass*|*admin*|*123456*|*sparky_prod*|*your_secret*)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

# 1. Validate mandatory application signing key (SECURITY_KEY)
if [ -z "$SECURITY_KEY" ] || [ "${#SECURITY_KEY}" -lt 32 ]; then
    echo "[Sparky LMS CRITICAL ERROR] SECURITY_KEY is missing or fewer than 32 characters." >&2
    echo "Generate a secure 64-char key using: openssl rand -hex 32" >&2
    exit 1
fi

if is_placeholder "$SECURITY_KEY"; then
    echo "[Sparky LMS CRITICAL ERROR] SECURITY_KEY contains a known insecure placeholder." >&2
    echo "You must generate a unique, cryptographically random key: openssl rand -hex 32" >&2
    exit 1
fi

# 2. Validate database password (DB_PASSWORD)
if [ -z "$DB_PASSWORD" ] || [ "${#DB_PASSWORD}" -lt 12 ]; then
    echo "[Sparky LMS CRITICAL ERROR] DB_PASSWORD is missing or fewer than 12 characters." >&2
    echo "Generate a secure database password: openssl rand -base64 24" >&2
    exit 1
fi

if is_placeholder "$DB_PASSWORD"; then
    echo "[Sparky LMS CRITICAL ERROR] DB_PASSWORD contains a known insecure placeholder." >&2
    echo "You must generate a unique, strong password: openssl rand -base64 24" >&2
    exit 1
fi

# 3. Validate Redis password (REDIS_PASSWORD)
if [ -z "$REDIS_PASSWORD" ] || [ "${#REDIS_PASSWORD}" -lt 12 ]; then
    echo "[Sparky LMS CRITICAL ERROR] REDIS_PASSWORD is missing or fewer than 12 characters." >&2
    echo "Generate a secure Redis password: openssl rand -base64 24" >&2
    exit 1
fi

if is_placeholder "$REDIS_PASSWORD"; then
    echo "[Sparky LMS CRITICAL ERROR] REDIS_PASSWORD contains a known insecure placeholder." >&2
    echo "You must generate a unique, strong password: openssl rand -base64 24" >&2
    exit 1
fi

echo "[Sparky LMS] Mandatory secrets validated successfully."

# 4. Generate runtime configuration.php from configuration.php.dist if absent
if [ ! -f "/var/www/sparky-lms/app/config/configuration.php" ] && [ -f "/var/www/sparky-lms/app/config/configuration.php.dist" ]; then
    echo "[Sparky LMS] Generating configuration.php from configuration.php.dist..."
    cp /var/www/sparky-lms/app/config/configuration.php.dist /var/www/sparky-lms/app/config/configuration.php
    chmod 644 /var/www/sparky-lms/app/config/configuration.php
fi

# 5. Verify PHP dependencies and autoload.php
if [ ! -f "/var/www/sparky-lms/vendor/autoload.php" ]; then
    echo "[Sparky LMS] vendor/autoload.php not found. Installing Composer dependencies..."
    if ! composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader; then
        echo "[Sparky LMS CRITICAL ERROR] composer install failed during container startup." >&2
        exit 1
    fi
fi

# 5. Ensure essential runtime writable directories exist
for dir in /var/www/sparky-lms/app/cache /var/www/sparky-lms/app/logs /var/www/sparky-lms/app/upload /var/www/sparky-lms/app/courses; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
    fi
done

# 6. Set least-privilege permissions: only mutable directories are writable by PHP-FPM (www-data)
# Application source code remains read-only to PHP-FPM (owned by root:root)
chown -R www-data:www-data /var/www/sparky-lms/app/cache /var/www/sparky-lms/app/logs /var/www/sparky-lms/app/upload /var/www/sparky-lms/app/courses 2>/dev/null || true
chmod -R 775 /var/www/sparky-lms/app/cache /var/www/sparky-lms/app/logs /var/www/sparky-lms/app/upload /var/www/sparky-lms/app/courses 2>/dev/null || true

echo "[Sparky LMS] Environment initialized. Executing: $@"
exec "$@"
