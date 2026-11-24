#!/bin/bash
# ==============================================================================
# Sparky LMS â€” Development SSL/TLS Certificate Generator
# Generates self-signed certificates for local development and testing.
# NEVER commit the generated key.pem to version control!
# ==============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SSL_DIR="${SCRIPT_DIR}"
KEY_FILE="${SSL_DIR}/key.pem"
CERT_FILE="${SSL_DIR}/cert.pem"

DOMAIN="${1:-localhost}"
DAYS="${2:-365}"

echo "[Sparky SSL] Checking certificates in ${SSL_DIR}..."

if [ -f "${KEY_FILE}" ] && [ -f "${CERT_FILE}" ]; then
    echo "[Sparky SSL] Certificates already exist:"
    echo "  - Private Key: ${KEY_FILE}"
    echo "  - Certificate: ${CERT_FILE}"
    exit 0
fi

if ! command -v openssl >/dev/null 2>&1; then
    echo "[Sparky SSL] ERROR: 'openssl' command is not installed or not in PATH."
    echo "[Sparky SSL] Please install OpenSSL or provide your own certificates in ${SSL_DIR}."
    exit 1
fi

echo "[Sparky SSL] Generating new self-signed certificate for '${DOMAIN}' (${DAYS} days)..."

openssl req -x509 -nodes -days "${DAYS}" -newkey rsa:2048 \
    -keyout "${KEY_FILE}" \
    -out "${CERT_FILE}" \
    -subj "/C=US/ST=Development/L=Local/O=SparkyLMS/CN=${DOMAIN}" \
    -addext "subjectAltName=DNS:${DOMAIN},DNS:*.${DOMAIN},IP:127.0.0.1"

chmod 600 "${KEY_FILE}" 2>/dev/null || true
chmod 644 "${CERT_FILE}" 2>/dev/null || true

echo "[Sparky SSL] Success! Development certificates generated:"
echo "  - Private Key: ${KEY_FILE} (permissions: 0600)"
echo "  - Certificate: ${CERT_FILE} (permissions: 0644)"
echo "[Sparky SSL] Reminder: These certificates are for development only. Use Let's Encrypt or trusted CA in production."
