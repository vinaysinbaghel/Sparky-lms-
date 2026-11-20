# Sparky LMS - Security Hardening & Production Hosting Guide

This guide details the security fixes applied to Sparky LMS following the **Deep Repository Readiness & Risk Audit (19 November 2026)**, clarifies the GitHub Secret Scanning alert, and provides step-by-step instructions for hosting Sparky LMS securely in production.

---

## 1. Audit Remediation Matrix (November 19, 2026)

All findings identified in the audit report have been fully remediated:

| Finding & Priority | Root Cause & Risk | Remediation Implemented | Status |
| :--- | :--- | :--- | :--- |
| **P0: CRITICAL — Course files may be directly retrievable outside LMS authorization** | `router.php`, `nginx.conf.sample`, and `.htaccess` mapped `/courses/` directly to `app/courses/` physical disk paths or aliases, bypassing enrollment and session checks. | Direct web access to `app/courses` is blocked (`403 Forbidden`). All course document, SCORM, and video requests route through `main/document/download.php` and `main/document/download_scorm.php`, strictly checking user enrollment, session, and permissions. Removed all public physical disk aliases. | **FIXED** |
| **P0: HIGH — Production Nginx sample has incomplete TLS configuration** | `listen 443 ssl http2` was enabled while certificate directives were commented out, causing `nginx -t` validation failure. | Rewrote `nginx.conf.sample` with two complete blocks: an HTTP bootstrap server block with Let's Encrypt ACME challenge support, and a complete TLS 1.2/1.3 production server block with valid certificate paths. | **FIXED** |
| **P0: HIGH — Docker Nginx published port 443 without SSL listener or certificates** | Docker published HTTPS port 443, but Nginx configuration only listened on HTTP port 80. | Configured dual port 80 & 443 SSL server blocks in [`docker/nginx.conf`](docker/nginx.conf) with TLS 1.2/1.3 and generated default self-signed development certificates in [`docker/ssl`](docker/ssl). | **FIXED** |
| **P0: HIGH — Docker Compose contained known fallback passwords** | `docker-compose.yml` had fallback defaults (`:-SparkySecurePass2026!`), creating default credential vulnerabilities if deployed without `.env`. | Removed all default password fallbacks. Enforced mandatory environment variable substitution (`${VAR:?Error}`) so Docker Compose fails fast if secrets are missing. Provided [`.env.example`](.env.example) and added `.env` to [`.gitignore`](.gitignore). | **FIXED** |
| **P0: HIGH — Dockerfile did not install Composer dependencies** | Dockerfile omitted `composer install`, leaving container without `vendor/autoload.php`. | Integrated multi-stage Composer build (`composer install --no-dev --optimize-autoloader`) and added runtime entrypoint script [`docker/entrypoint.sh`](docker/entrypoint.sh) to ensure dependencies and folder permissions. | **FIXED** |
| **P0: HIGH — Redis eviction policy can evict active sessions** | `maxmemory-policy allkeys-lru` permitted Redis to evict session keys under memory pressure, logging out students unexpectedly. `save ""` wiped data on restart. | Configured Redis session store with `maxmemory-policy noeviction`, persistence (`save 900 1`, `appendonly yes`), and mandatory password authentication (`requirepass`). | **FIXED** |
| **P0: HIGH — Composer security advisory blocking is disabled** | `config.audit.block-insecure` was set to `false`. | Enabled `"block-insecure": true` under Composer audit configuration. | **FIXED** |
| **P0: HIGH — PHP version contract inconsistent with Composer** | `composer.json` declared `php: ^7.4` while docs advertised PHP 8.1 - 8.3. | Updated `composer.json` requirement to `"php": "^8.1 || ^8.2 || ^8.3"`, pinned platform PHP to `8.3.0`, and aligned `composer.lock`. | **FIXED** |
| **P0: HIGH — Published test claim not reproducible from checkout** | Tests were documented in an uncommitted/gitignored `scratch/` folder. | Created tracked test suite [`tests/run_test_suite.php`](tests/run_test_suite.php) executing 101 automated tests. Added GitHub Actions CI workflow [`.github/workflows/sparky-verification.yml`](.github/workflows/sparky-verification.yml). | **FIXED** |
| **P0: HIGH — Docker deployment instructions did not match repository** | `docker-compose.yml` and `Dockerfile` were missing; references pointed to non-existent `./sparky-1.0` subfolder. | Created working root [`docker-compose.yml`](docker-compose.yml), [`Dockerfile`](Dockerfile) (PHP 8.3-FPM with all extensions), and [`docker/nginx.conf`](docker/nginx.conf) with health checks. | **FIXED** |
| **P0: HIGH — README installation paths and clone placeholders** | Clone instructions used placeholders and `/var/www/sparky/sparky-1.0`. | Standardized all paths to `/var/www/sparky-lms` directly from repository root and updated repository URL to `https://github.com/vinaysinbaghel/Sparky-lms-.git`. | **FIXED** |
| **P1: MEDIUM — Session cookie settings are recommendations, not enforced defaults** | Runtime code didn't assert secure cookie flags at startup. | Enforced `session.cookie_httponly = 1`, `session.cookie_secure = 1`, `session.use_only_cookies = 1`, and `session.use_strict_mode = 1` in [`main/inc/lib/chamilo_session.class.php`](main/inc/lib/chamilo_session.class.php). | **FIXED** |
| **P1: MEDIUM — Router CORS policy overly broad for course media** | Wildcard `Access-Control-Allow-Origin: *` was set on media streaming. | Replaced wildcard with origin validation against `APP_HOST` and same-origin host headers. | **FIXED** |
| **P1: MEDIUM — Database dump encoding incompatible with Linux/Docker** | SQL dump was encoded in UTF-16 LE (752KB) with BOM, causing Linux `mysql` CLI syntax failures. | Converted [`database/sparky_clean_database.sql`](database/sparky_clean_database.sql) to clean UTF-8 text (376KB). | **FIXED** |
| **P1: OBSERVATION — Missing configuration template** | `app/config/configuration.php` was gitignored with no clean environment template. | Created [`app/config/configuration.php.dist`](app/config/configuration.php.dist) with dynamic environment variable support (`DB_HOST`, `DB_USER`, `REDIS_PASSWORD`, etc.). | **FIXED** |

---

## 2. GitHub Secret Scanning Alert Resolution

### What Triggered the Alert?
- **Secret Detected:** `Stripe Test API Secret Key (sk_test_***)`
- **Location reported by GitHub:** `plugin/.../stripe-php/README.md:56`

### Root Cause & Dismissal
1. `sk_test_***` was **Stripe's official public documentation sandbox key** from the upstream `stripe-php` library sample README. It does not belong to any active account or live financial data.
2. The `plugin/buycourses/gateways/stripe-php` directory was deleted in commit `7db4d6bec3`.
3. GitHub Secret Scanning flags historical commits in the Git graph.
4. **Action:** Navigate to [GitHub Security > Secret scanning](https://github.com/vinaysinbaghel/Sparky-lms-/security/secret-scanning) and close the alert as **"Used in tests"** or **"False positive"**.

---

## 3. Production Hosting Checklist

### A. Web Server Setup
- **Nginx:** Use [`nginx.conf.sample`](nginx.conf.sample) or [`docker/nginx.conf`](docker/nginx.conf).
- **Apache:** Ensure `mod_rewrite`, `mod_headers`, and `mod_expires` are enabled, and `AllowOverride All` is set on `/var/www/sparky-lms`.

### B. Database Initialization
1. Create a MySQL / MariaDB database and user:
   ```sql
   CREATE DATABASE sparky_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'sparky_user'@'localhost' IDENTIFIED BY 'StrongRandomPasswordHere!';
   GRANT ALL PRIVILEGES ON sparky_lms.* TO 'sparky_user'@'localhost';
   FLUSH PRIVILEGES;
   ```
2. Import the clean schema:
   ```bash
   mysql -u sparky_user -p sparky_lms < database/sparky_clean_database.sql
   ```
3. Change default `admin` password upon first login.

### C. Application Configuration
Copy template and set permissions:
```bash
cp app/config/configuration.php.dist app/config/configuration.php
chmod 640 app/config/configuration.php
sudo chown www-data:www-data app/config/configuration.php
```

### D. Directory Permissions
```bash
cd /var/www/sparky-lms
sudo chown -R www-data:www-data /var/www/sparky-lms
sudo find . -type f -exec chmod 644 {} +
sudo find . -type d -exec chmod 755 {} +
sudo chmod -R 775 app/upload app/courses app/cache app/logs
```

### E. Run Automated Test Verification
```bash
php tests/run_test_suite.php
```
Ensure all 101 verification tests pass before launching public traffic.
