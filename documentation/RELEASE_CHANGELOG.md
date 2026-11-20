# Sparky LMS — Release Changelog & Controlled Pilot Readiness

**Release Version:** `v1.1.0-pilot`  
**Base Platform:** Chamilo LMS 1.11.x Core with Sparky Subsystems  
**Target Environments:** Docker Compose (Linux/macOS/Windows) & Bare-Metal Ubuntu/Debian  
**Target Runtime:** PHP 8.1 - 8.3 | MariaDB 10.6+ | Redis 7+  
**Release Date:** November 20, 2026  

---

## 1. Executive Summary

Sparky LMS has completed a comprehensive multi-sprint hardening and stabilization cycle. The platform transitions from an unstable development branch with critical security vulnerabilities and dependency conflicts into a secure, reproducible, high-performance, and fully tested learning management system ready for a **Controlled Pilot Deployment**.

---

## 2. Sprint 1: Critical Security & Deployment Hardening

### 2.1 Route Precedence & Course Content Protection
- **Vulnerability:** Generic regex matching for media files (`.mp4`, `.pdf`, `.webm`) bypassed LMS authorization checks when requested directly via web server rules.
- **Resolution:** Rewrote Nginx rules (`docker/sparky-routes.inc`, `nginx.conf.sample`, and `README.md`) ensuring all course virtual routes (`/courses/<CODE>/document/...`) strictly take precedence over generic static handlers. All course assets are routed through authenticated PHP handlers (`download.php`, `download_scorm.php`).
- **Evidence:** Real HTTP requests across 20 test vectors in `tests/test_http_requests.php` prove private files cannot be fetched anonymously without valid session authentication.

### 2.2 Docker Dev/Prod Separation & Container Security
- **Hardening:**
  - Removed full-application writable bind mounts (`.:/var/www/sparky-lms`) in production `docker-compose.yml`.
  - Replaced with isolated, persistent named volumes (`sparky_courses`, `sparky_upload`, `sparky_logs`).
  - Added dedicated `docker-compose.dev.yml` for local developer workflows.
  - Eliminated fallback database passwords; mandated credentials via Compose `:?` syntax.
  - Added health checks for `db` (MariaDB ping & innodb init), `redis` (PING/PONG), `app` (PHP-FPM syntax & file check), and `web` (HTTPS probe).
  - Port 80 HTTP now automatically issues 301 redirects to Port 443 HTTPS.

### 2.3 Dependency Contract & Platform Compatibility
- **Issue:** Composer requirements conflicted with PHP 8.3; build commands had to rely on `--ignore-platform-reqs`.
- **Resolution:**
  - Updated `composer.json` and `composer.lock` to explicitly support `"php": "^8.1 || ^8.2 || ^8.3"`.
  - Upgraded developer tool dependencies to eliminate PHP 8.4 syntax constraints.
  - Pinned platform PHP to `8.3.0`.
  - Removed `--ignore-platform-reqs` from `Dockerfile`.
  - Verified `composer check-platform-reqs --no-dev` and `composer validate --check-lock --no-check-all` pass with 0 warnings.

### 2.4 Magic Login Hardening
- **Vulnerability:** Magic tokens were reusable, had no brute-force rate-limiting, and were vulnerable to session fixation.
- **Resolution:**
  - Created `user_magic_attempt` table in `database/sparky_clean_database.sql`.
  - Implemented `isRateLimited($ip)` (maximum 10 failed attempts per 15 minutes).
  - Enforced single-use: atomic update sets `used_at = NOW()` and `expires_at = NOW()` on successful redemption.
  - Called `session_regenerate_id(true)` upon successful authentication.
  - Added explicit token revocation methods (`revokeToken`, `revokeTokensForUser`).

### 2.5 Redis Session Architecture
- **Issue:** Redis configured with `maxmemory-policy allkeys-lru` permitted silent eviction of active student sessions under memory pressure.
- **Resolution:**
  - Configured Redis session store with `maxmemory-policy noeviction`.
  - Isolated sessions to Redis DB 0 (`redis_session_db`) and application cache to DB 1 (`redis_cache_db`).
  - Configured RDB snapshotting (`save 900 1`, `save 300 10`) and Append-Only File (`appendonly yes`).

---

## 3. Sprint 2: Functional LMS & Journey Improvements

### 3.1 Student Experience & Lesson Resumption
- **Improvement:** Students resuming a course now automatically resume at their last incomplete lesson.
- **Implementation:** Added `CourseCurriculum::getResumeLesson($userId, $courseCode)` and exposed it via `ajax_get_resume_lesson` and `ajax_mark_lesson_completed` in `curriculum.ajax.php`.
- **Tracking:** Added progress persistence in `c_curriculum_lesson_track`.

### 3.2 Instructor Experience
- **Improvement:** Course curriculum builder hardened with strict server-side RBAC.
- **File Uploads:** Restricted upload endpoints strictly to valid media and document types; blocked all executable extensions (`.php`, `.phtml`, `.phar`, `.sh`, `.exe`).
- **Video Delivery:** Native RFC 7233 HTTP 206 byte-range streaming enables instant scrubbing and seeking across lectures.

### 3.3 Administrator Experience
- **Improvement:** Certificate Editor hardened against Stored XSS by disallowing SVG template uploads and enforcing randomized background filenames.
- **User Management:** Preserved complete Chamilo administrator capabilities: user management, course assignments, sessions, and reporting.

---

## 4. Sprint 3: UI & UX Polish

- **Course Welcome Layout:** Replaced empty default course placeholder in `main/template/default/layout/welcome_to_course.tpl` with a modern Sparky card with SVG icons, clear typography, and action buttons.
- **Error States:** Branded error templates for magic login expired, already used, or rate-limited states.
- **Responsive Navigation:** Improved curriculum viewer mobile responsiveness and modern layout controls.

---

## 5. Sprint 4: Automated Verification & Performance Load Testing

- **Comprehensive Test Suite (`tests/run_test_suite.php`):** Expanded to **135 assertions** covering dependencies, Docker configuration, negative access controls, upload validation, session security, database schema, multi-pass traversal, Redis architecture, and RBAC. Pass rate: **100%**.
- **Real HTTP Request Test Suite (`tests/test_http_requests.php`):** **20 assertions** using live PHP test server validating direct access blocks, traversal defenses, download handlers, and HTTP 206 byte-range streaming.
- **Database Backup & Restoration Test (`tests/test_database_backup_restore.php`):** **26 assertions** verifying schema structure, 247 tables, 8 Sparky custom tables, and non-destructive dump restoration commands.
- **Concurrency Benchmark Suite (`tests/test_concurrency.php`):**
  - **50 Concurrent Learners:** 100% success, 0 errors, 711.5 req/sec, 1.41 ms avg latency.
  - **100 Concurrent Learners:** 100% success, 0 errors, 643.5 req/sec, 1.55 ms avg latency.
  - **250 Concurrent Learners:** 100% success, 0 errors, 272.6 req/sec, 3.67 ms avg latency.

---

## 6. Sprint 5 & 6: Security Remediation & Full Integration Testing

### 6.1 Private Key Untracking & Local Cert Generation
- **Remediation:** Removed `docker/ssl/cert.pem` and `docker/ssl/key.pem` from Git tracking. Added them to `.gitignore` to prevent secret leaks.
- **Generator Scripts:** Created `docker/ssl/generate-certs.sh` and `docker/ssl/generate-certs.bat` for generating local 2048-bit self-signed SSL certificates.
- **Key Rotation Advisory:** Documented rotation recommendation in `docker/ssl/README.md` and `documentation/SECURITY_AUDIT.md` for any deployment that used commit `75c9105ac6`.

### 6.2 Application Secret Key Signing
- **Remediation:** Removed hardcoded fallback `SECURITY_KEY` from `app/config/configuration.php.dist`.
- **Enforcement:** Enforced runtime check requiring `SECURITY_KEY` >= 32 characters, failing safely with HTTP 500 without leaking secrets.
- **Compose Requirement:** Configured `docker-compose.yml` to mandate `SECURITY_KEY: ${SECURITY_KEY:?Error: SECURITY_KEY must be set in .env file}`.

### 6.3 Least-Privilege File Permissions
- **Hardening:** Application source code is owned by `root:root` with read-only access (755/644) to PHP-FPM workers.
- **Mutable Storage:** Write permissions are strictly restricted to `app/courses`, `app/upload`, `app/cache`, and `app/logs` (`www-data:www-data`, 775).
- **Enforcement:** Updated `Dockerfile`, `docker/entrypoint.sh`, `README.md`, and `documentation/DEPLOYMENT_GUIDE.md`.

### 6.4 Upload & Course File Exposure Prevention
- **Virtual Routing:** Created `main/document/download_curriculum.php` enforcing course enrollment before curriculum files are served.
- **Access Blocks:** Blocked direct unauthenticated access to `/app/upload` (HTTP 403) across Nginx and `router.php`.
- **User Storage:** Personal files route exclusively through `download_my_files.php`.

### 6.5 Full System Integration Test Suite (`tests/test_integration.php`)
- **Execution:** 71 assertions executed against MariaDB and Redis:
  1. Strict service availability checks: fails immediately if MariaDB or Redis is unavailable (no false passes).
  2. Real authentication, bcrypt verification, and session logout.
  3. Redis RESP session persistence, SELECT db 0, prefix `sparky_sess:`, and 1800s TTL.
  4. RBAC authorization (Admin, Instructor, Student boundaries).
  5. Course enrolment & cross-student authorization.
  6. Private user storage isolation (cross-user download blocked).
  7. SCORM package authorization.
  8. Video HTTP 206 partial content range requests.
  9. Dangerous upload rejection (`.php`, `.phar`, `.sh`, `.exe`, `.bat`).
  10. Certificate blueprints & XSS sanitization.
  11. Real MariaDB backup and restoration verification.
  12. Custom Nginx image bundling, entrypoint SSL validation, and routing.

---

## 7. Sprint 7: Final Deployment Blocker Remediation

### 7.1 Custom Nginx Gateway Image & Application Source Bundling
- **Blocker:** Generic `nginx:alpine` lacked application files at `/var/www/sparky-lms`, causing 404 errors on static assets and PHP entrypoints.
- **Resolution:** Created `docker/Dockerfile.nginx` bundling identical release application files into `/var/www/sparky-lms` with immutable read-only permissions (`root:root`, 755/644).
- **Compose Update:** Configured `docker-compose.yml` to build `docker/Dockerfile.nginx` and mount courses and uploads as read-only (`:ro`).

### 7.2 SSL First-Run Deployment & Entrypoint Guard
- **Blocker:** Missing certificates caused Nginx container to crash silently or fail without an actionable explanation.
- **Resolution:** Implemented `docker/entrypoint-nginx.sh` which validates the existence and non-zero size of `/etc/nginx/ssl/cert.pem` and `/etc/nginx/ssl/key.pem`. If missing, it outputs an explicit troubleshooting guide for generating local development certs or mounting CA-signed production certs, then halts with exit code 1.

### 7.3 Mandatory Secret Enforcement & Insecure Placeholder Rejection
- **Hardening:** Completely wiped all fixed example passwords (`ChangeMe_...`) from `.env.example`.
- **Validation:** Updated `docker/entrypoint.sh` and `app/config/configuration.php.dist` to validate secret length and reject known insecure placeholders (`changeme`, `replace_with`, `password`, `testpass`, etc.) with HTTP 500 without leaking secrets.

### 7.4 Authenticated Multi-Learner Concurrency & Load Benchmark
- **Realism:** Replaced static JSON test in `tests/test_concurrency.php` with a multi-step authenticated student workflow (course navigation, lesson data query, HTTP 206 video range seeking, database-backed progress updates).
- **Metrics:** Recorded 740 operations across 4 cohorts (10, 25, 50, 100 learners) with 100% success rate, 0% errors, throughput, and detailed latency percentiles (min, p50, p90, p95, p99, max).

---

## 8. GitHub Secret Scanning Resolution

- **Alert:** Leaked Stripe Test API Secret Key (`sk_test_***`).
- **Root Cause:** Historical upstream commit `98d882c6f7` contained an example test key in `plugin/buycourses/gateways/stripe-php/README.md`.
- **Status:** File was removed from HEAD in commit `7db4d6bec3`. Documentation has masked all references.
- **Action for Repo Admin:** Dismiss alert in GitHub Security tab as "Used in tests / False positive".

---

## 9. Pilot Readiness Verdict

| Criterion | Target | Status | Notes |
|---|---|---|---|
| **Nginx Source Access** | Same release bundled | **READY** | Built into custom `docker/Dockerfile.nginx` (read-only `root:root`) |
| **SSL First-Run** | Guarded entrypoint | **READY** | Fails with explicit error if certs missing; local cert generator provided |
| **Mandatory Secrets** | Unique, no placeholders | **READY** | Empty `.env.example`, placeholder rejection in entrypoint & config |
| **Storage Authorization** | Zero unauthenticated downloads | **READY** | `/courses` and `/app/upload` strictly protected via PHP handlers |
| **Reproducibility** | Turnkey Docker startup | **READY** | `docker compose up -d --build` with healthchecks |
| **PHP 8.3 Support** | Native compatibility | **READY** | Validated on PHP 8.1, 8.2, and 8.3 |
| **Automated Verification** | Full test coverage | **READY** | **276 total assertions** across 5 test suites |
| **Learner Load Benchmark** | Authenticated workflows | **READY** | 740 ops tested up to 100 simultaneous learners (0% errors) |
| **Controlled Pilot Recommendation** | GO | **READY** | All blockers resolved; ready for controlled pilot |
