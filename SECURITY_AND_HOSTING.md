# Sparky LMS - Security Hardening & Production Hosting Guide

This guide details the security fixes applied to Sparky LMS, clarifies the GitHub Secret Scanning alert, and provides step-by-step instructions for hosting Sparky LMS securely in production.

---

## 1. GitHub Secret Scanning Alert Resolution

### What Triggered the Alert?
- **Alert:** `Stripe Test API Secret Key (sk_test_BQokikJ0vBiI2HlwgH4...)`
- **Location reported by GitHub:** `plugin/.../stripe-php/README.md:56`

### Root Cause
1. `sk_test_BQokikJOvBiI2HlWgH4olfQ2` is **Stripe's official public sandbox key** provided directly in Stripe's developer documentation and sample READMEs for testing integrations.
2. It was included as part of upstream documentation inside `plugin/buycourses/gateways/stripe-php/`.
3. The entire folder was already removed from the active codebase in commit `7db4d6bec3`.
4. However, GitHub's automated scanner inspects the **entire Git commit graph** (all historical commits). When scanned, GitHub flagged the old commit.

### How to Dismiss the Alert on GitHub
Because this key is a public documentation test key and not a live private credential:
1. Go to your repository on GitHub: [`https://github.com/vinaysinbaghel/Sparky-lms-/security/secret-scanning`](https://github.com/vinaysinbaghel/Sparky-lms-/security/secret-scanning).
2. Click on the open alert for **Stripe Test API Secret Key**.
3. In the top-right corner, click **Close as** -> select **"Used in tests"** or **"False positive"**.
4. Add an optional comment: *"Stripe official public documentation sample key in upstream legacy vendor commit; library deleted from active code."*

---

## 2. Security Hardening Applied to Codebase

The following vulnerabilities have been remediated:

| Vulnerability / Risk | Severity | Affected File | Resolution Applied |
| :--- | :--- | :--- | :--- |
| **Unrestricted File Upload (RCE risk)** | **Critical** | `main/inc/lib/course_curriculum.lib.php` | Implemented strict extension whitelist (`mp4, webm, pdf, docx, zip, etc.`). Explicitly blocked all executable extensions (`.php*, .phar, .phtml, .sh, .cgi, .exe`). Added directory protection (`.htaccess` disabling PHP execution). |
| **Broken Access Control & IDOR** | **High** | `main/inc/ajax/curriculum.ajax.php` | Enforced strict role-based checks. Write actions (`upload_media`, `delete_chapter`, `delete_lesson`) are strictly restricted to Platform Admins and Course Teachers. |
| **Direct Web Access to Database & Sensitive Files** | **High** | `router.php`, `.htaccess`, `web.config` | Direct HTTP access to `/database/`, `app/config/`, `app/logs/`, `*.sql`, `*.bat`, `*.sh`, `composer.json`, etc. is now denied with `403 Forbidden`. |
| **Stored XSS via SVG Backgrounds** | **Medium** | `main/admin/certificate_editor.php` | Disallowed raw SVG file uploads for certificate templates to prevent malicious script injection. |
| **Hardcoded Windows Path Exposure** | **Low** | `start-server.bat` | Refactored hardcoded local user paths to use environment variables (`%LOCALAPPDATA%`) and system `PATH`. |

---

## 3. Production Hosting Checklist

### A. Web Server Setup
- **Nginx:** Use the provided [`nginx.conf.sample`](nginx.conf.sample) which includes fastcgi routing, video/audio 206 Partial Content byte ranges, and rules blocking sensitive files.
- **Apache:** Ensure `mod_rewrite`, `mod_headers`, and `mod_expires` are enabled, and `AllowOverride All` is set for the document root so [`.htaccess`](.htaccess) takes effect.

### B. Database Setup
1. Create a MySQL / MariaDB database and dedicated user:
   ```sql
   CREATE DATABASE sparkylms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'sparky_user'@'localhost' IDENTIFIED BY 'StrongRandomPasswordHere!';
   GRANT ALL PRIVILEGES ON sparkylms.* TO 'sparky_user'@'localhost';
   FLUSH PRIVILEGES;
   ```
2. Import the clean schema:
   ```bash
   mysql -u sparky_user -p sparkylms < database/sparky_clean_database.sql
   ```
3. **Important:** Change the default administrator password immediately after first login via `/main/admin/user_information.php` or update the admin hash.

### C. Application Configuration
Create `app/config/configuration.php` with appropriate database credentials and security keys. Restrict permissions:
```bash
chmod 640 app/config/configuration.php
chown www-data:www-data app/config/configuration.php
```

### D. Recommended `php.ini` Settings
```ini
display_errors = Off
display_startup_errors = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
expose_php = Off

upload_max_filesize = 512M
post_max_size = 512M
memory_limit = 512M
max_execution_time = 300

session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

### E. Directory Permissions
Ensure web server user (`www-data`, `nginx`, or `apache`) has write access only to necessary upload and cache directories:
```bash
chmod -R 755 .
chmod -R 775 app/upload app/courses app/cache app/logs
```
