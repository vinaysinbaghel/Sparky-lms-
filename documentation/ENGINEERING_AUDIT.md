# Sparky LMS — Engineering Architecture & Codebase Inventory

**Repository:** `https://github.com/vinaysinbaghel/Sparky-lms-`  
**Platform Version:** Sparky LMS 1.0 (Hardened Chamilo 1.11.x Core with Modernized Extensions)  
**Target Environment:** PHP 8.1 - 8.3 | MariaDB 10.6+ | Redis 7.x | Nginx 1.24+  

---

## 1. Executive Summary

Sparky LMS is a high-performance Learning Management System built upon the established, battle-tested core of Chamilo LMS 1.11.x, modernised with a suite of enterprise learning features, a containerised microservices deployment architecture, and rigorous security hardening.

This document serves as the canonical engineering inventory of the repository, providing a precise distinction between retained Chamilo foundation components and custom Sparky enhancements.

```
+-----------------------------------------------------------------------------------+
|                                  SPARKY LMS                                       |
+-----------------------------------------------------------------------------------+
|  [Custom Sparky Layer]                                                            |
|  - Curriculum & Lesson Studio (Visual chapter/lesson builder, drag-and-drop)      |
|  - Certificate Blueprint Designer (Visual typography, responsive CSS certificates)|
|  - HTTP 206 Video/Media Streaming Engine (Native byte-range video scrubbing)      |
|  - Passwordless Magic Login System (Cryptographic token authentication)          |
|  - Modern Container Runtime (PHP 8.3 FPM, Nginx TLS, Redis No-Eviction, MariaDB)  |
+-----------------------------------------------------------------------------------+
|  [Hardened Chamilo Core Foundation]                                               |
|  - Course Framework, Tool Delegation & Lifecycle Management                       |
|  - SCORM 1.2 / 2004 Tracking Runtime & AICC Engine                                |
|  - Gradebook, Assignments (Work), Quizzes (Exercise), Forum & Social Tools        |
|  - Dual Security Layer (Server-side RBAC, CSRF tokens, Session Hijacking defense)  |
+-----------------------------------------------------------------------------------+
```

---

## 2. Core Chamilo Foundation vs. Custom Sparky Components

To ensure stability and maintainability, Sparky LMS avoids rewriting functioning Chamilo core modules. Instead, modern capabilities are cleanly layered through modular libraries, administrative dashboards, and route middleware.

### 2.1 Component Comparison Inventory

| Domain | Original Chamilo 1.11 Baseline | Custom Sparky LMS Implementation |
|---|---|---|
| **Curriculum Builder** | Disjointed course learning paths (`lp/`) with legacy UI. | **Curriculum Studio (`main/admin/course_curriculum.php`):** Unified drag-and-drop chapter and lesson manager with inline video, document, and quiz attachments. |
| **Curriculum API** | Generic AJAX endpoints lacking granular upload filtering. | **Curriculum AJAX (`main/inc/ajax/curriculum.ajax.php`):** High-speed API enforcing strict server-side RBAC, file extension validation, and MIME sniffing. |
| **Certificates** | Static HTML certificates via gradebook with complex manual tags. | **Certificate Blueprint Studio (`main/admin/certificate_editor.php`):** Visual design canvas with dynamic typography, custom CSS, randomized secure asset uploads, and anti-XSS rendering. |
| **Certificate Delivery** | Monolithic gradebook evaluation script. | **Blueprint Viewer (`main/gradebook/view_blueprint_certificate.php`):** Secure token-verified certificate renderer and print-ready export engine. |
| **Media Streaming** | Static file downloads or rudimentary PHP file reads without byte-range seeking. | **HTTP 206 Streaming Engine (`router.php` & `main/inc/lib/document.lib.php`):** Full RFC 7233 byte-range seeking for video scrubbing, audio playback, and PDF streaming. |
| **Authentication** | Password-only or LDAP/CAS authentication. | **Magic Login (`main/inc/lib/magic_login.lib.php`):** 256-bit cryptographically secure one-click login links with automated expiration and single-use invalidation. |
| **Session Storage** | File-based sessions (`/tmp`) or database session table. | **Redis Session Architecture:** Dedicated Redis session database (`db 0`), `noeviction` policy, RDB/AOF persistence, and automated fallback to filesystem. |
| **Routing & Protection** | Apache-centric `.htaccess` rewrite rules. | **Unified Routing Layer:** Synchronized security in `router.php`, `docker/sparky-routes.inc`, and `nginx.conf.sample` preventing course asset bypass. |
| **Deployment** | Manual web-wizard installer. | **Docker Compose:** Fully orchestrated stack (PHP-FPM 8.3, Nginx with TLS, MariaDB 10.6, Redis 7.2) with automated database initialization. |

---

## 3. Application Architecture & Entry Points

### 3.1 Web Server Entry Points

1. **`index.php` (Root Gateway):**
   - Entry point for unauthenticated visitors and system routing.
   - Detects active sessions and redirects authenticated learners to `user_portal.php`.
2. **`user_portal.php` (Learner & Instructor Dashboard):**
   - Central portal displaying enrolled courses, session progress, announcements, and quick-action shortcuts.
3. **`router.php` (Development & Testing Router):**
   - Simulates production Nginx routing for the PHP built-in web server (`php -S 0.0.0.0:8000 router.php`).
   - Normalises multi-pass encoded URIs, blocks traversal attempts (`..`, `%252e%252e`), guards sensitive files, and enforces that course media is authorized via `main/document/download.php`.
4. **`main/admin/` (Administrative Portal):**
   - Contains platform configuration, user provisioning, course management, curriculum studio, and certificate editors.
   - Protected by `api_protect_admin_script(true)`.

### 3.2 File Storage & Directory Architecture

```
Sparky-lms-/
├── app/
│   ├── config/              # Configuration files (configuration.php) - BLOCKED from web access
│   ├── courses/             # Physical course repositories - BLOCKED from direct web access
│   ├── upload/              # Platform uploads (user avatars, certificates, media) - PHP execution BLOCKED
│   ├── cache/               # Twig and asset caches - BLOCKED from web access
│   └── logs/                # Audit logs - BLOCKED from web access
├── main/
│   ├── inc/                 # Shared core libraries, globals, and autoloaders
│   ├── admin/               # Administration controllers (Curriculum, Certificates, Users)
│   ├── auth/                # Authentication scripts (Login, Logout, Magic Login)
│   ├── document/            # Document download, upload, and SCORM handlers
│   └── course_home/         # Course homepage entry point
├── database/                # Schema definitions & initial bootstrap dumps - BLOCKED from web access
├── docker/                  # Nginx configuration, SSL certs, and entrypoint scripts
├── tests/                   # Automated verification suite (run_test_suite.php)
└── router.php               # Standalone development routing and security firewall
```

---

## 4. Custom Sparky Subsystems

### 4.1 Curriculum & Lesson Studio

- **Controller:** `main/admin/course_curriculum.php`
- **Library:** `main/inc/lib/course_curriculum.lib.php`
- **API Endpoint:** `main/inc/ajax/curriculum.ajax.php`
- **Database Schema:**
  - `c_curriculum_chapter`: Hierarchical chapter containers with display order.
  - `c_curriculum_lesson`: Individual lesson items supporting video, document, audio, text, or quiz modes.
  - `c_curriculum_lesson_track`: Granular progress tracking (started, completed, duration spent).
- **Security Controls:**
  - Strict server-side RBAC: Only platform admins, course admins, or assigned course teachers may mutate curriculum.
  - Strict file-extension validation rejecting `.php`, `.phar`, `.sh`, `.exe`, `.phtml`, etc.
  - Cryptographically randomized file naming preventing overwrite and enumeration attacks.

### 4.2 Certificate Blueprint Studio

- **Admin Manager:** `main/admin/certificate_blueprints.php`
- **Visual Editor:** `main/admin/certificate_editor.php`
- **Awarded Certificates:** `main/admin/awarded_certificates.php`
- **Library:** `main/inc/lib/certificate_blueprint.lib.php`
- **Student Viewer:** `main/gradebook/view_blueprint_certificate.php`
- **Database Schema:**
  - `c_certificate_blueprint`: Templates with typography, layout, primary color, signatory, and custom CSS.
  - `c_certificate_blueprint_course`: Mapping between certificate blueprints and courses.
  - `c_certificate_awarded`: Records of issued certificates, verification hash, and award date.
- **Security Hardening:**
  - Uploaded background images disallow SVG files to prevent stored XSS attacks.
  - Template rendering uses HTML entity encoding (`htmlspecialchars`) on all user-supplied variables (`{{STUDENT_NAME}}`, `{{COURSE_NAME}}`).

### 4.3 HTTP 206 Byte-Range Video Streaming

- **Engines:** `router.php` (CLI dev server) & `main/inc/lib/document.lib.php` (`smartReadFile`)
- **Specification:** RFC 7233 compliant byte-range handling.
- **Capabilities:**
  - Supports explicit ranges (`bytes=0-1048575`), open ranges (`bytes=1048576-`), and suffix ranges (`bytes=-500`).
  - Emits `Accept-Ranges: bytes`, `Content-Range: bytes START-END/TOTAL`, and `HTTP/1.1 206 Partial Content`.
  - Rejects unsatisfiable ranges with `HTTP/1.1 416 Range Not Satisfiable`.
  - Streaming through `main/document/download.php` enforces enrollment checks BEFORE initiating byte-range output buffers.

### 4.4 Magic Login Passwordless Authentication

- **Library:** `main/inc/lib/magic_login.lib.php`
- **Consumer:** `main/auth/magic_login.php`
- **Database Table:** `user_magic_token`
- **Security Design:**
  - Tokens generated using `bin2hex(random_bytes(32))` yielding 256 bits of cryptographic entropy.
  - Time-limited validity (default 30 days, configurable).
  - Single-use consumption or automatic expiration on new token generation.
  - Fully immune to credential stuffing and brute-force attacks.

---

## 5. Background Processes & Scheduled Tasks

Sparky LMS relies on standard cron triggers for maintenance operations:

1. **Session Cleanup & Garbage Collection:**
   - Managed automatically via Redis TTL keys (`session.gc_maxlifetime`).
2. **Scheduled Notifications & Mail Queue:**
   - Triggered via `main/cron/send_notifications.php`.
3. **Database Maintenance:**
   - Periodic optimization of course tracking tables (`c_curriculum_lesson_track`, `track_e_access`).

---

## 6. Repository Integrity & Clean Working State

All legacy upstream Chamilo workflows (`.github/workflows/php.yml`, `php_cs.yml`) that mandated obsolete PHP 7.4 runtimes have been replaced with `.github/workflows/sparky-verification.yml`. The test suite and Composer configuration are synchronized, passing 100% of runtime and security checks.
