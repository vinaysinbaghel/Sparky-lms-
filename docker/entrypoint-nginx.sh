#!/bin/sh
# ==============================================================================
# Sparky LMS â€” Production Nginx Gateway Entrypoint
# Validates SSL certificates, directory permissions, and configuration syntax.
# ==============================================================================
set -e

echo "[Sparky Nginx Gateway] Starting initialization..."

SSL_DIR="/etc/nginx/ssl"
CERT_FILE="${SSL_DIR}/cert.pem"
KEY_FILE="${SSL_DIR}/key.pem"

# 1. Enforce SSL Certificate presence
if [ ! -f "${CERT_FILE}" ] || [ ! -f "${KEY_FILE}" ]; then
    echo "==========================================================================" >&2
    echo "[Sparky Nginx CRITICAL ERROR] TLS/SSL certificate or private key missing!" >&2
    echo "Expected files:" >&2
    echo "  - Certificate: ${CERT_FILE}" >&2
    echo "  - Private Key: ${KEY_FILE}" >&2
    echo "" >&2
    echo "FOR LOCAL DEVELOPMENT:" >&2
    echo "  Generate local self-signed certificates before starting containers:" >&2
    echo "    bash docker/ssl/generate-certs.sh (Linux/macOS)" >&2
    echo "    docker\\ssl\\generate-certs.bat    (Windows)" >&2
    echo "" >&2
    echo "FOR PRODUCTION:" >&2
    echo "  Mount your valid CA-signed certificates (e.g. Let's Encrypt) in docker-compose.yml:" >&2
    echo "    volumes:" >&2
    echo "      - /etc/letsencrypt/live/yourdomain/fullchain.pem:/etc/nginx/ssl/cert.pem:ro" >&2
    echo "      - /etc/letsencrypt/live/yourdomain/privkey.pem:/etc/nginx/ssl/key.pem:ro" >&2
    echo "==========================================================================" >&2
    exit 1
fi

# 2. Verify certificates are non-empty
if [ ! -s "${CERT_FILE}" ] || [ ! -s "${KEY_FILE}" ]; then
    echo "[Sparky Nginx CRITICAL ERROR] Certificate or private key file is 0 bytes empty!" >&2
    exit 1
fi

# 3. Validate Nginx configuration syntax
if ! nginx -t -q 2>&1; then
    echo "[Sparky Nginx CRITICAL ERROR] Nginx syntax validation failed!" >&2
    nginx -t
    exit 1
fi

echo "[Sparky Nginx Gateway] Configuration and SSL certificates verified. Launching Nginx..."
exec "$@"
