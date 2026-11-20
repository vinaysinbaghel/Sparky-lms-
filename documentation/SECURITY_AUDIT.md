# Sparky LMS — Comprehensive Security Audit & Remediation Report

**Date of Audit:** November 2026  
**Audited Target:** Sparky LMS 1.0 (`https://github.com/vinaysinbaghel/Sparky-lms-`)  
**Security Posture:** Hardened for Controlled Pilot / Production Deployment  

---

## 1. Executive Summary

A comprehensive, defense-in-depth security audit of the Sparky LMS application code, configuration templates, container definitions, and routing architecture was conducted. All identified vulnerabilities and architectural flaws have been resolved, verified with automated regression tests, and hardened against modern threat vectors.

### 1.1 Remediation Summary Table

| Finding ID | Severity | Category | Vulnerability Description | Status | Verification Reference |
|---|---|---|---|---|---|
| **SEC-01** | **CRITICAL** | Authorization / Bypass | Course documents and videos bypassed LMS enrollment checks via generic Nginx/Router media rules. | **RESOLVED** | Course rewrites ordered before static media; direct access blocked. |
| **SEC-02** | **HIGH** | Remote Code Execution | Unrestricted upload potential in curriculum lesson assets permitting `.php`/`.phar` upload. | **RESOLVED** | Strict extension blacklist & MIME validation in `CourseCurriculum`. |
| **SEC-03** | **HIGH** | Stored XSS | SVG background uploads in certificate editor allowed embedded `<script>` execution. | **RESOLVED** | SVG disallowed; only raster formats (`jpg, png, webp, gif`) accepted. |
| **SEC-04** | **HIGH** | Session Management | Redis `allkeys-lru` policy silently evicted active user sessions under memory pressure. | **RESOLVED** | Replaced with `noeviction`, isolated DB 0, and RDB/AOF persistence. |
| **SEC-05** | **MEDIUM** | Path Traversal | Multi-pass encoded URIs (`%252e%252e`) could potentially evade traversal filters. | **RESOLVED** | Iterative URL decoding, null-byte rejection, and `assertSafePath()` canonical check. |
| **SEC-06** | **MEDIUM** | Broken Access Control | Curriculum AJAX endpoints lacked strict server-side role validation for write actions. | **RESOLVED** | Enforced `$canEdit` (platform admin or course teacher) check returning HTTP 403. |
| **SEC-07** | **MEDIUM** | Insecure Defaults | Docker Compose contained default fallback passwords in variable interpolations. | **RESOLVED** | Mandated explicit `.env` variables using Docker Compose `:?` syntax. |
| **SEC-08** | **LOW** | Information Disclosure | Direct web access to `.sql` dumps, `.env`, `composer.lock`, and `app/config/`. | **RESOLVED** | Explicit 403 blocking rules across Nginx, `.htaccess`, `web.config`, and `router.php`. |
| **SEC-09** | **INFO** | Secret Scanning | GitHub Secret Scanning alert for Stripe test key in historical documentation. | **DOCUMENTED** | Public Stripe doc key from upstream 2021 commit; resolution steps documented below. |

---

## 2. Deep Vulnerability Analysis & Hardening Details

### 2.1 SEC-01: Course Asset Authorization & Media Rule Precedence

- **Vulnerability Mechanism:**  
  In web servers and development routers, generic regex rules matching media extensions (e.g., `.(mp4|webm|pdf)$`) often take precedence over course-specific routing. An attacker could request `/courses/MATH101/document/private_lecture.mp4` directly, causing the static media streaming handler to serve the file without checking whether the user was authenticated or enrolled in `MATH101`.
- **Remediation Implementation:**  
  1. In `router.php`, the byte-range streaming handler (`serveMediaWithRangeSupport`) was relocated **strictly below** all `/courses/...` rewrite rules.
  2. In `nginx.conf.sample` and `docker/sparky-routes.inc`, all course paths (`location ~* ^/courses/([^/]+)/(.*)$`) are defined **before** the generic media streaming block (`location ~* \.(mp4|webm|pdf)$`).
  3. All requests to course documents now flow through `main/document/download.php`, which executes `api_protect_course_script(true)` to enforce course enrollment, session validity, and file visibility permissions before invoking the HTTP 206 streaming engine.

### 2.2 SEC-02: Curriculum Asset Upload & RCE Prevention

- **Vulnerability Mechanism:**  
  Allowing arbitrary file uploads in curriculum management risks Remote Code Execution (RCE) if an attacker uploads executable PHP files (`.php`, `.phtml`, `.phar`) or shell scripts (`.sh`, `.bat`).
- **Remediation Implementation:**  
  In `main/inc/lib/course_curriculum.lib.php`, `CourseCurriculum::handleUploadedFile()` enforces:
  - Strict extension denial for dangerous extensions:
    ```php
    $blocked = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phar', 'exe', 'sh', 'bat', 'cmd', 'cgi', 'pl', 'py'];
    ```
  - Filename sanitization and replacement with cryptographically secure random names:
    ```php
    $safeName = 'lesson_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    ```
  - Storage outside public web roots or in directories where PHP execution is disabled at the web-server level (`location ~* ^/app/upload/.*\.ph` -> `return 403;`).

### 2.3 SEC-03: Certificate Editor Stored XSS Mitigation

- **Vulnerability Mechanism:**  
  Certificate templates allow instructors to customize certificate backgrounds. If Scalable Vector Graphics (`.svg`) files are permitted, malicious JavaScript embedded in `<svg><script>alert(1)</script></svg>` executes in the browser of any student or administrator viewing the certificate.
- **Remediation Implementation:**  
  1. In `main/admin/certificate_editor.php`, background uploads are restricted strictly to raster formats:
     ```php
     $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
     ```
  2. In `main/inc/lib/certificate_blueprint.lib.php`, template interpolation runs `htmlspecialchars()` on all dynamic variables (`{{STUDENT_NAME}}`, `{{COURSE_NAME}}`, `{{DATE}}`) to prevent script injection.

### 2.4 SEC-04: Redis Session Management & Eviction Hardening

- **Vulnerability Mechanism:**  
  The default `maxmemory-policy allkeys-lru` allows Redis to evict least-recently-used keys when memory limits are reached. When Redis acts as a PHP session store, key eviction silently destroys active user sessions, abruptly logging out active students during exams or lectures.
- **Remediation Implementation:**  
  1. **Eviction Policy:** Configured `--maxmemory-policy noeviction`. Under extreme memory exhaustion, Redis returns an OOM error on new write attempts rather than silently evicting active student sessions.
  2. **Automated Expiry:** Redis automatically cleans up expired sessions using the configured session lifetime TTL (`session.gc_maxlifetime = 86400`).
  3. **Database Separation:** Configured dedicated Redis database 0 for user sessions (`sparky_sess:`) and database 1 for disposable application caching (`sparky_cache:`).
  4. **Persistence:** Configured RDB point-in-time snapshots (`--save 900 1 --save 300 10`) combined with Append-Only File logging (`--appendonly yes`) backed by a dedicated Docker volume (`redis_data`), ensuring sessions survive container restarts.
  5. **Resilient Fallback:** `app/config/configuration.php.dist` tests Redis connectivity; if Redis is unavailable, PHP falls back gracefully to standard filesystem session storage.

### 2.5 SEC-05: Multi-Pass Directory Traversal & Canonical Path Defense

- **Vulnerability Mechanism:**  
  Attackers often employ multi-pass URL encoding (e.g., `%252e%252e%252f` -> `%2e%2e/` -> `../`) or null bytes (`\0`, `%00`) to bypass single-pass string matching filters.
- **Remediation Implementation:**  
  In `router.php`:
  1. Iterative decoding normalizes up to 5 rounds of URL encoding:
     ```php
     $decodedUri = $rawUri;
     $maxDecodes = 5;
     while ($maxDecodes-- > 0 && strpos($decodedUri, '%') !== false) {
         $next = rawurldecode($decodedUri);
         if ($next === $decodedUri) break;
         $decodedUri = $next;
     }
     ```
  2. Strict rejection of null bytes in both raw and decoded strings.
  3. Canonical filesystem path validation via `assertSafePath($file)`:
     ```php
     $realFile = realpath($file);
     $realDocRoot = realpath(__DIR__);
     if (!$realFile || strpos(str_replace('\\', '/', $realFile), str_replace('\\', '/', $realDocRoot)) !== 0) {
         http_response_code(403);
         exit;
     }
     ```

### 2.6 SEC-06: Curriculum API Role-Based Access Control (RBAC)

- **Vulnerability Mechanism:**  
  Missing server-side permission checks on mutating endpoints allow authenticated learners to tamper with curriculum content or upload arbitrary media by directly invoking AJAX actions.
- **Remediation Implementation:**  
  In `main/inc/ajax/curriculum.ajax.php`:
  - Enforced authentication: `if ($currentUserId <= 0) { http_response_code(401); exit; }`.
  - Enforced editing rights:
    ```php
    $canEdit = api_is_platform_admin() || api_is_course_admin() || CourseManager::is_course_teacher($currentUserId, $courseCode);
    $writeActions = ['ajax_upload_lesson_media', 'upload_media', 'ajax_delete_chapter', 'ajax_delete_lesson'];
    if (in_array($action, $writeActions, true) && !$canEdit) {
        http_response_code(403);
        exit;
    }
    ```

### 2.7 SEC-07: Docker Compose Credential Hardening

- **Vulnerability Mechanism:**  
  Docker Compose files utilizing fallback syntax like `${DB_PASSWORD:-DefaultSecret}` allow containers to launch with known, publicly documented credentials if the administrator fails to configure `.env`.
- **Remediation Implementation:**  
  Replaced all default fallbacks in `docker-compose.yml` with mandatory error-checking syntax:
  ```yaml
  MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:?Error: MYSQL_ROOT_PASSWORD must be set in .env file}
  MYSQL_PASSWORD: ${DB_PASSWORD:?Error: DB_PASSWORD must be set in .env file}
  REDIS_PASSWORD: ${REDIS_PASSWORD:?Error: REDIS_PASSWORD must be set in .env file}
  ```
  Containers will refuse to build or boot unless the administrator explicitly provides strong passwords in `.env`.

---

## 3. GitHub Secret Scanning Alert: Stripe Test API Key

### 3.1 Alert Context

- **Detected Secret:** `Stripe Test API Secret Key` (`sk_test_***`)
- **Detection Location:** `plugin/buycourses/src/gateways/stripe-php/README.md:56`
- **Origin Commit:** Commit `98d882c6f7` (Nov 22, 2021) in upstream Chamilo repository (`add stripe libs`).
- **Working Tree Status:** **Not present in the repository working tree.** The file and directory were completely removed in upstream commit `7db4d6bec3` (Feb 3, 2022).
- **Security Assessment:**  
  `sk_test_***` was **Stripe's official public documentation sandbox key**, published in Stripe's open-source SDK sample files. It is not associated with any private bank account, live customer data, or live transactions.

### 3.2 Resolution Instructions for Repository Administrator

Because the commit resides in historical Git objects and the key is an upstream sample test key, dismiss the alert in GitHub:

1. Navigate to the GitHub repository: `https://github.com/vinaysinbaghel/Sparky-lms-/security/secret-scanning`.
2. Locate the alert for **Stripe Test API Secret Key**.
3. Click **Close alert** or **Dismiss alert**.
4. Select the reason: **Used in tests** or **False positive (documentation sample from upstream library)**.
5. Add a comment: *"Historical public sample key from upstream stripe-php SDK documentation in 2021 commit, removed from working tree in 2022."*
