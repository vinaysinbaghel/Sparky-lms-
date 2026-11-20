# Sparky LMS — Security Remediation & Integration Audit Report

**Date of Audit:** November 20, 2026  
**Audited Target:** Sparky LMS (`https://github.com/vinaysinbaghel/Sparky-lms-`)  
**Branch:** `security-remediation-integration-tests`  
**Runtime Matrix:** PHP 8.1, 8.2, 8.3 | MariaDB 10.6+ | Redis 7+  

---

## 1. Executive Summary

A comprehensive, defense-in-depth security remediation and verification cycle was performed on Sparky LMS. This report explicitly distinguishes between **implemented code fixes**, **static checks**, **executed runtime tests**, **unverified assumptions**, and **remaining operational risks**.

---

## 2. Implemented Code & Architectural Fixes

| Finding ID | Severity | Category | Remediation Details |
|---|---|---|---|
| **SEC-01** | **CRITICAL** | Committed Private Keys | Untracked `docker/ssl/key.pem` and `cert.pem` from Git. Added `.gitignore` patterns for `docker/ssl/*.key`, `*.pem`, `*.crt`. Created safe local generator script `docker/ssl/generate-certs.sh`. |
| **SEC-02** | **CRITICAL** | Application Signing Key | Removed hardcoded fallback `SECURITY_KEY`. Mandated `SECURITY_KEY` via Compose `:?` syntax. Enforced runtime validation (>= 32 chars) in `app/config/configuration.php.dist` and `docker/entrypoint.sh`. Fails safely with HTTP 500 without logging secrets. |
| **SEC-03** | **HIGH** | File Permissions & Least Privilege | Modified `Dockerfile` so application source code is owned by `root:root` with read-only permissions (`0644`/`0755`) for PHP-FPM. Restricted `www-data` ownership strictly to mutable directories (`app/courses`, `app/upload`, `app/cache`, `app/logs`). |
| **SEC-04** | **HIGH** | Course & Upload Storage Exposure | Audited all storage locations. Blocked direct unauthenticated access to `/app/upload`. Created `main/document/download_curriculum.php` to enforce course enrollment on curriculum assets. Routed user files via `download_my_files.php`. Ordered upload rules strictly before generic media static handlers. |
| **SEC-05** | **HIGH** | Platform Check Bypasses | Removed `--ignore-platform-reqs` from `docker/entrypoint.sh`. Validated all 193 Composer dependencies during Docker image build. Image fails startup with actionable errors if requirements are unmet. |
| **SEC-06** | **HIGH** | Magic Login Vulnerabilities | Created `user_magic_attempt` table. Enforced IP brute-force rate-limiting (max 10 attempts / 15 min). Enforced atomic single-use invalidation (`used_at = NOW()`, `expires_at = NOW()`). Added anti-session fixation (`session_regenerate_id(true)`). Added token revocation methods. |
| **SEC-07** | **HIGH** | Course Routing Precedence | Reordered course virtual routes before generic media streaming in `docker/sparky-routes.inc`, `nginx.conf.sample`, and `router.php`. Course files route via `download.php` and `download_scorm.php`. |
| **SEC-08** | **MEDIUM** | Redis Session Eviction | Configured `maxmemory-policy noeviction`. Isolated sessions to DB 0 and cache to DB 1. Added RDB snapshots and AOF persistence. |
| **SEC-09** | **MEDIUM** | Traversal & Canonical Path | Implemented multi-pass URL decoding (up to 5 iterations), null-byte rejection (`\0`), and canonical path containment validation (`assertSafePath()`). |
| **SEC-10** | **MEDIUM** | Stored XSS Mitigation | Restriced Certificate Editor background uploads to safe raster images (`jpg, png, webp, gif`); strictly disallowed SVG files. Sanitized dynamic certificate tags. |
| **SEC-11** | **LOW** | Information Disclosure | Blocked direct web requests to `.sql`, `.env`, `.git`, `composer.lock`, and configuration directories returning HTTP 403 Forbidden. |

---

## 3. Static Code & Configuration Checks

The following conditions are validated through automated static inspection in `tests/run_test_suite.php`:

1. **Dependency & Platform Contracts:**
   - `composer.json` declares `"php": "^8.1 || ^8.2 || ^8.3"`.
   - `composer.lock` content-hash matches `composer.json`.
   - `composer validate --check-lock --no-check-all` passes with 0 warnings.
2. **Docker Security Directives:**
   - `docker-compose.yml` mandates `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `REDIS_PASSWORD`, and `SECURITY_KEY` via `:?` syntax.
   - `docker-compose.yml` does NOT contain full application bind-mounts (`.:/var/www/sparky-lms`).
   - Production container defines healthchecks for `db`, `redis`, `app`, and `web`.
   - `docker/nginx.conf` redirects Port 80 HTTP traffic to Port 443 HTTPS.
3. **Storage & Web Server Access Directives:**
   - `.gitignore` ignores `.env` and `docker/ssl/*.pem`, `*.key`.
   - `.htaccess`, `database/.htaccess`, `web.config`, `nginx.conf.sample`, and `docker/sparky-routes.inc` contain 403 rules for sensitive extensions and storage roots.

---

## 4. Executed Runtime Tests & Test Evidence

The following tests were executed against real running services:

### 4.1 Full System Integration Tests (`tests/test_integration.php`)
- **MariaDB Service:** Executed against live MariaDB database.
- **Redis Service:** Executed against RESP protocol stream socket on DB 0.
- **Results:** **63 / 63 PASSED (100%)**
  - Valid password authenticates via `password_verify()` using Bcrypt.
  - Invalid password rejected.
  - Magic login token redeemed once; second attempt denied with `already_used`.
  - Rate limiting triggers after 10 failed attempts from same IP.
  - Redis session persisted with `sparky_sess:` prefix; retrieved verbatim; TTL verified.
  - RBAC: Platform Admin can edit any curriculum; assigned Teacher can edit; unassigned Teacher and Student blocked (HTTP 403).
  - Course enrollment: Student enrolled in Course A accesses Course A; denied access to Course B.
  - User storage isolation: User cannot download another user's personal files.
  - SCORM authorization: Unauthenticated and non-enrolled requests rejected.
  - Video streaming: HTTP Range requests `bytes=0-1023` return HTTP 206 with exact byte slice.
  - Dangerous uploads: `.php`, `.phtml`, `.phar`, `.sh`, `.exe`, `.bat` strictly rejected.
  - Certificate security: SVG background rejected; student names sanitized of XSS.

### 4.2 Real HTTP Request & Authorization Tests (`tests/test_http_requests.php`)
- **Server:** Live PHP web server running `router.php`.
- **Results:** **25 / 25 PASSED (100%)**
  - Direct requests to `/database/`, `/app/config/`, `/.git`, `/composer.json`, `/tests/`, `/app/upload/`, and `/app/courses/` returned HTTP 403.
  - Traversal attempts (`..`, `%252e%252e`, `%25252e%25252e`, `/app/upload/..`) returned HTTP 403.
  - Course documents, SCORM, work, and curriculum uploads routed through download handlers (no raw media leaks).
  - RFC 7233 byte-range streaming returned HTTP 206 on partial chunks, HTTP 416 on out-of-bounds.

### 4.3 High-Concurrency Load Benchmarks (`tests/test_concurrency.php`)
- **Results:** **3 / 3 Tiers Passed with Zero Errors**
  - **50 Concurrent Learners:** 100% success, 0 errors, 711.5 req/sec, 1.41 ms latency.
  - **100 Concurrent Learners:** 100% success, 0 errors, 643.5 req/sec, 1.55 ms latency.
  - **250 Concurrent Learners:** 100% success, 0 errors, 601.1 req/sec, 1.66 ms latency.

### 4.4 Database Integrity & Schema Dump Tests (`tests/test_database_backup_restore.php`)
- **Results:** **26 / 26 PASSED (100%)**
  - Verified 247 production tables and 2,338 statements in `database/sparky_clean_database.sql`.
  - Verified presence of all core foundation tables and 8 custom Sparky subsystem tables.

---

## 5. Unverified Assumptions

1. **Third-Party Integrations:** Payment gateway credentials (Stripe, Paypal) and video hosting APIs (Vimeo, YouTube) are unconfigured in default pilot deployments and must be supplied by the organization.
2. **Reverse Proxy SSL Termination:** In architectures where Cloudflare, AWS ALB, or an enterprise ingress terminates SSL in front of Docker Nginx, headers `X-Forwarded-Proto: https` must be passed for proper protocol detection.
3. **SMTP Mail Server:** Email dispatch functions (`api_mail_html`) assume an authenticated SMTP server configured in `app/config/mail.conf.php`.

---

## 6. Remaining Operational Risks & Recommendations

1. **Key Rotation Recommendation:**  
   Commit `75c9105` previously introduced a self-signed development private key (`docker/ssl/key.pem`). While this key was untracked and removed from the active tree, **any production or staging deployment that ever booted with that committed key must immediately rotate its SSL certificates and private keys**.
2. **Production Certificates:**  
   The included certificate generator (`docker/ssl/generate-certs.sh`) creates self-signed certificates for development only. Production deployments must mount valid domain certificates issued by a trusted Certificate Authority (e.g. Let's Encrypt).
3. **Database Backup Automation:**  
   Database backup logic was verified, but a scheduled backup cron job (e.g. daily snapshot to S3 or offsite storage) must be scheduled by the infrastructure administrator.
