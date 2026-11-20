@echo off
REM ==============================================================================
REM Sparky LMS — Development SSL/TLS Certificate Generator (Windows)
REM ==============================================================================
setlocal

set "SSL_DIR=%~dp0"
set "KEY_FILE=%SSL_DIR%key.pem"
set "CERT_FILE=%SSL_DIR%cert.pem"

if exist "%KEY_FILE%" if exist "%CERT_FILE%" (
    echo [Sparky SSL] Certificates already exist in %SSL_DIR%
    exit /b 0
)

where openssl >nul 2>nul
if %ERRORLEVEL% neq 0 (
    echo [Sparky SSL] ERROR: openssl is not in your PATH.
    echo Please install OpenSSL or provide cert.pem and key.pem in %SSL_DIR%
    exit /b 1
)

echo [Sparky SSL] Generating self-signed certificates for localhost...
openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout "%KEY_FILE%" -out "%CERT_FILE%" -subj "/CN=localhost"
if %ERRORLEVEL% equ 0 (
    echo [Sparky SSL] Certificates successfully generated in %SSL_DIR%
) else (
    echo [Sparky SSL] Failed to generate certificates.
    exit /b 1
)
