# Sparky LMS — Enterprise Learning Management System

[![PHP Version](https://img.shields.io/badge/PHP-8.1%20|%208.2%20|%208.3-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Database](https://img.shields.io/badge/Database-MariaDB%2010.6+%20|%20MySQL%208.0-003545?logo=mariadb&logoColor=white)](https://mariadb.org/)
[![Web Server](https://img.shields.io/badge/Web%20Server-Nginx%201.20+-009639?logo=nginx&logoColor=white)](https://nginx.org/)
[![Cache & Session](https://img.shields.io/badge/Cache%20%26%20Session-Redis%206.0+-DC382D?logo=redis&logoColor=white)](https://redis.io/)
[![Streaming](https://img.shields.io/badge/Media%20Engine-HTTP%20206%20Partial%20Content-4F46E5)](https://tools.ietf.org/html/rfc7233)
[![License](https://img.shields.io/badge/License-GNU%20GPL%20v3-blue.svg)](license.txt)

Sparky LMS is a modern, high-performance, open-source Learning Management System engineered for enterprise training, professional certifications, and institutional education. Built with a sleek learner-centric interface, Sparky LMS delivers seamless course management, interactive curriculum structuring, native HTML5 byte-range video scrubbing, automated certificate templating, and lightning-fast Redis-backed session handling.

---

## Table of Contents

- [1. Architecture Overview](#1-architecture-overview)
- [2. System Requirements & Dependencies](#2-system-requirements--dependencies)
- [3. Production Installation (Ubuntu / Debian Linux)](#3-production-installation-ubuntu--debian-linux)
  - [Step 1: Install Operating System Packages & PHP 8.3](#step-1-install-operating-system-packages--php-83)
  - [Step 2: Install and Configure MariaDB Database](#step-2-install-and-configure-mariadb-database)
  - [Step 3: Install and Configure Redis](#step-3-install-and-configure-redis)
  - [Step 4: Clone & Configure Sparky LMS](#step-4-clone--configure-sparky-lms)
  - [Step 5: Set Directory Permissions](#step-5-set-directory-permissions)
  - [Step 6: Configure Nginx with HTTP 206 Streaming](#step-6-configure-nginx-with-http-206-streaming)
  - [Step 7: Configure Redis Session Handler in PHP-FPM](#step-7-configure-redis-session-handler-in-php-fpm)
  - [Step 8: Set Up Cron Jobs](#step-8-set-up-cron-jobs)
  - [Step 9: Enable HTTPS via Let's Encrypt SSL](#step-9-enable-https-via-lets-encrypt-ssl)
- [4. Docker & Docker Compose Deployment](#4-docker--docker-compose-deployment)
- [5. Local Development Quickstart](#5-local-development-quickstart)
- [6. Configuration & Environment Variables](#6-configuration--environment-variables)
- [7. Video Streaming & Partial Content (HTTP 206)](#7-video-streaming--partial-content-http-206)
- [8. Security & Production Hardening](#8-security--production-hardening)
- [9. Automated Verification & Testing](#9-automated-verification--testing)
- [10. Documentation Index](#10-documentation-index)
- [11. Troubleshooting & FAQ](#11-troubleshooting--faq)

---

## 1. Architecture Overview

Sparky LMS follows a scalable, decoupled architecture designed for high concurrency:

```text
               [ Internet / HTTPS (Port 443) ]
                             │
                             ▼
                ┌─────────────────────────┐
                │       Nginx Edge        │  <-- SSL Termination, Static Cache,
                │   Reverse Proxy / HTTP  │      HTTP 206 Byte-Range Streaming
                └────────────┬────────────┘
                             │ FastCGI (Unix Socket / Port 9000)
                             ▼
                ┌─────────────────────────┐
                │       PHP-FPM 8.3       │  <-- Sparky Core App & Custom Modules
                │   (Sparky Application)  │      OPcache Accelerated
                └──────┬───────────┬──────┘
                       │           │
       SQL Queries /   │           │ TCP / UNIX Socket (Port 6379)
       PDO Connection  ▼           ▼
        ┌──────────────────┐   ┌───────────────────────────┐
        │  MariaDB 10.6+   │   │         Redis 7.x         │
        │ (InnoDB utf8mb4) │   │ (Fast Sessions & Caching) │
        └──────────────────┘   └───────────────────────────┘
```

- **Web Tier (Nginx):** Terminates TLS, enforces security rules, handles static assets directly with aggressive caching, and serves byte-range chunks for video/audio seek requests.
- **Application Tier (PHP-FPM):** Executes LMS logic, course catalog queries, curriculum hierarchies, and certificate generation with Zend OPcache.
- **Session & Caching Tier (Redis):** Offloads user sessions from disk/database to high-speed in-memory storage, enabling instant session lookups and frictionless horizontal clustering.
- **Data Tier (MariaDB):** Stores platform tables, course structures, tracking logs, extra fields, and certificate blueprints in `utf8mb4`.

---

## 2. System Requirements & Dependencies

### Core Stack
| Component | Minimum Version | Recommended Version | Purpose |
| :--- | :--- | :--- | :--- |
| **Operating System** | Ubuntu 22.04 LTS / Debian 12 | Ubuntu 24.04 LTS / Debian 12 | Enterprise host environment |
| **PHP** | PHP 8.1 | **PHP 8.3** | Core application engine |
| **Web Server** | Nginx 1.18+ / Apache 2.4 | **Nginx 1.24+** | Reverse proxy, FastCGI, HTTP 206 video seeking |
| **Database** | MariaDB 10.6+ / MySQL 8.0+ | **MariaDB 11.4** | utf8mb4 storage engine |
| **In-Memory Cache** | Redis 6.0+ | **Redis 7.2** | User session management and object caching |
| **TLS/SSL** | TLS 1.2 | TLS 1.3 | Encrypted communication |

### Required PHP Extensions
Sparky LMS requires the following PHP modules:
- `php8.3-fpm` (FastCGI process manager for Nginx)
- `php8.3-mysql` / `pdo_mysql` (MariaDB/MySQL database drivers)
- `php8.3-redis` (High-speed session and caching driver)
- `php8.3-gd` (Certificate rendering, badge creation, and image manipulation)
- `php8.3-intl` (Internationalization and multi-language support)
- `php8.3-curl` (External integrations and API client calls)
- `php8.3-mbstring` (Multibyte UTF-8 string processing)
- `php8.3-zip` (Course export/import, SCORM extraction, certificate bundles)
- `php8.3-xml` & `php8.3-simplexml` (Document parsing and data exchange)
- `php8.3-opcache` (Bytecode caching for maximum execution speed)
- `php8.3-fileinfo` (MIME-type detection for uploaded files and media)

---

## 3. Production Installation (Ubuntu / Debian Linux)

Follow this step-by-step procedure to deploy Sparky LMS on a production Linux server.

### Step 1: Install Operating System Packages & PHP 8.3

Update apt repositories and install PHP 8.3 with all necessary extensions:

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip ufw

# Add Ondřej Surý PHP repository (standard for Debian/Ubuntu)
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install PHP 8.3 and dependencies
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis \
    php8.3-gd php8.3-intl php8.3-curl php8.3-mbstring php8.3-zip \
    php8.3-xml php8.3-opcache php8.3-fileinfo
```

Tune PHP configuration (`/etc/php/8.3/fpm/php.ini`):
```ini
upload_max_filesize = 500M
post_max_size = 500M
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
date.timezone = UTC
display_errors = Off
log_errors = On
error_log = /var/log/php8.3-fpm.log

; OPcache performance tuning
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.revalidate_freq = 2
opcache.validate_timestamps = 0
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
sudo systemctl enable php8.3-fpm
```

---

### Step 2: Install and Configure MariaDB Database

Install MariaDB server:
```bash
sudo apt install -y mariadb-server mariadb-client
sudo systemctl start mariadb
sudo systemctl enable mariadb
```

Secure the database installation and create the Sparky database:
```bash
sudo mysql_secure_installation
```

Log in to MariaDB and create the database and user:
```sql
sudo mariadb -u root -p
```
```sql
CREATE DATABASE sparky_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sparky_user'@'localhost' IDENTIFIED BY 'Strong_Secure_Password_Here!';
GRANT ALL PRIVILEGES ON sparky_lms.* TO 'sparky_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

### Step 3: Install and Configure Redis

Install the Redis server:
```bash
sudo apt install -y redis-server
```

Edit `/etc/redis/redis.conf` to configure safe, dedicated session persistence:
```conf
supervised systemd
requirepass Strong_Secure_Redis_Password_Here!
maxmemory 512mb
maxmemory-policy noeviction
save 900 1
save 300 10
appendonly yes
```

Restart and verify Redis:
```bash
sudo systemctl restart redis-server
sudo systemctl enable redis-server
redis-cli -a Strong_Secure_Redis_Password_Here! ping
# Output: PONG
```

---

### Step 4: Clone & Configure Sparky LMS

Deploy the repository to `/var/www/sparky-lms`:
```bash
sudo mkdir -p /var/www/sparky-lms
sudo git clone https://github.com/vinaysinbaghel/Sparky-lms-.git /var/www/sparky-lms
cd /var/www/sparky-lms
```

Copy the production configuration template:
```bash
cp app/config/configuration.php.dist app/config/configuration.php
chmod 640 app/config/configuration.php
sudo chown www-data:www-data app/config/configuration.php
```

Set up environment variables in `/etc/environment` or via a `.env` loader:
```bash
sudo bash -c 'cat >> /etc/environment <<EOF
DB_HOST="127.0.0.1"
DB_PORT="3306"
DB_NAME="sparky_lms"
DB_USER="sparky_user"
DB_PASSWORD="Strong_Secure_Password_Here!"
APP_HOST="learn.yourdomain.com"
REDIS_HOST="127.0.0.1"
REDIS_PORT="6379"
REDIS_PASSWORD="Strong_Secure_Redis_Password_Here!"
EOF'
```

---

### Step 5: Set Directory Permissions

Ensure the web server user (`www-data`) owns and can write to the application cache, upload, home, and course repositories:

```bash
cd /var/www/sparky-lms
sudo chown -R www-data:www-data /var/www/sparky-lms
sudo find /var/www/sparky-lms -type f -exec chmod 644 {} +
sudo find /var/www/sparky-lms -type d -exec chmod 755 {} +

# Writable directories for uploads, cache, and user files
sudo chmod -R 775 app/cache app/upload app/home courses
```

---

### Step 6: Configure Nginx with HTTP 206 Streaming

Install Nginx:
```bash
sudo apt install -y nginx
```

Create `/etc/nginx/sites-available/sparky-lms.conf`:

```nginx
server {
    listen 80;
    server_name learn.yourdomain.com;
    root /var/www/sparky-lms;
    index index.php index.html;

    client_max_body_size 512M;
    client_body_buffer_size 128k;

    access_log /var/log/nginx/sparky_access.log combined buffer=64k flush=5m;
    error_log /var/log/nginx/sparky_error.log warn;

    # Gzip compression for text assets
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/rss+xml font/truetype font/opentype application/vnd.ms-fontobject image/svg+xml;

    # Security: Block sensitive directories & internal files
    location ~ ^/(tests|\.git|\.github|database|bin|scratch|app/config|app/logs|app/cache|app/courses) {
        deny all;
        return 403;
    }

    # Security: Block database backups, scripts, configs, lockfiles
    location ~* \.(sql|bat|sh|yml|yaml|dist|lock|log|env|ini|md)$ {
        deny all;
        return 403;
    }

    # Security: Prevent execution of PHP in writable upload directories
    location ~* ^/app/(upload|courses|cache|logs)/.*\.php$ {
        deny all;
        return 403;
    }

    # Static Assets with browser caching
    location ~* \.(jpg|jpeg|gif|png|webp|svg|ico|css|js|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
        try_files $uri =404;
    }

    # Video & Audio Streaming with HTTP 206 Byte-Range Seeking Support
    location ~* \.(mp4|webm|mkv|mov|ogg|mp3|wav|m4a|pdf)$ {
        expires 7d;
        add_header Cache-Control "public";
        add_header Accept-Ranges bytes;
        try_files $uri =404;
    }

    # Certificate Verification Route
    location ~ ^/certificates/?$ {
        rewrite ^/certificates/?$ /certificates/index.php last;
    }

    # Course Virtual Route Rewriting
    location ~ ^/courses/([^/]+)/?$ {
        rewrite ^/courses/([^/]+)/?$ /main/course_home/course_home.php?cDir=$1 last;
    }

    # Protected Course Downloads (Enforces student enrollment & LMS authentication)
    location ~* ^/courses/([^/]+)/(.*)$ {
        rewrite ^/courses/([^/]+)/(.*)$ /main/document/download.php?doc_url=/$2&cDir=$1 last;
    }

    # Core Application Gateway
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # PHP-FPM FastCGI Handler
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_param HTTP_PROXY "";
        
        # Buffer tuning for large responses
        fastcgi_buffer_size 128k;
        fastcgi_buffers 256 16k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_temp_file_write_size 256k;
        fastcgi_read_timeout 300s;
    }
}
```

Enable the configuration and test Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/sparky-lms.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

### Step 7: Configure Redis Session Handler in PHP-FPM

To handle high student concurrency safely, route PHP sessions directly to Redis with authentication:

Edit `/etc/php/8.3/fpm/pool.d/www.conf`:
```ini
php_value[session.save_handler] = redis
php_value[session.save_path] = "tcp://127.0.0.1:6379?auth=Strong_Secure_Redis_Password_Here!&prefix=sparky_sess:&timeout=2.0"
```

Restart PHP-FPM:
```bash
sudo systemctl restart php8.3-fpm
```

Now, all active student and admin sessions will be managed in Redis with microsecond access times and persist without eviction.

---

### Step 8: Set Up Cron Jobs

Sparky LMS relies on cron tasks for scheduled notifications, agenda synchronizations, and reporting.

Add the following to the `www-data` user's crontab (`sudo crontab -u www-data -e`):

```cron
# Process platform agenda notifications every hour
0 * * * * php /var/www/sparky-lms/main/cron/agenda.php > /dev/null 2>&1

# Run daily maintenance and archive tasks at 02:00 AM
0 2 * * * php /var/www/sparky-lms/main/cron/cleanup.php > /dev/null 2>&1
```

---

### Step 9: Enable HTTPS via Let's Encrypt SSL

Use Certbot to provision free automated SSL certificates:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d learn.yourdomain.com
```

Certbot will configure TLS 1.3 encryption and automatic certificate renewals.

---

## 4. Docker & Docker Compose Deployment

For containerized environments (AWS ECS, Google Cloud Run, DigitalOcean, self-hosted Docker), a turnkey `docker-compose.yml` and `Dockerfile` are provided in the repository root:

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: sparky_app
    restart: unless-stopped
    volumes:
      - .:/var/www/sparky-lms
    environment:
      DB_HOST: db
      DB_PORT: 3306
      DB_NAME: ${DB_NAME:-sparky_lms}
      DB_USER: ${DB_USER:-sparky_user}
      DB_PASSWORD: ${DB_PASSWORD:?Error: DB_PASSWORD must be set in .env file}
      APP_HOST: ${APP_HOST:-localhost}
      REDIS_HOST: redis
      REDIS_PORT: 6379
      REDIS_PASSWORD: ${REDIS_PASSWORD:?Error: REDIS_PASSWORD must be set in .env file}
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy

  web:
    image: nginx:alpine
    container_name: sparky_web
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - .:/var/www/sparky-lms:ro
      - ./docker/nginx.conf:/etc/nginx/conf.d/default.conf:ro
      - ./docker/sparky-routes.inc:/etc/nginx/conf.d/sparky-routes.inc:ro
      - ./docker/ssl:/etc/nginx/ssl:ro
    depends_on:
      - app

  db:
    image: mariadb:11.4
    container_name: sparky_db
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:?Error: MYSQL_ROOT_PASSWORD must be set in .env file}
      MYSQL_DATABASE: ${DB_NAME:-sparky_lms}
      MYSQL_USER: ${DB_USER:-sparky_user}
      MYSQL_PASSWORD: ${DB_PASSWORD:?Error: DB_PASSWORD must be set in .env file}
    volumes:
      - mariadb_data:/var/lib/mysql
      - ./database/sparky_clean_database.sql:/docker-entrypoint-initdb.d/01_init.sql:ro
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    container_name: sparky_redis
    restart: unless-stopped
    environment:
      REDIS_PASSWORD: ${REDIS_PASSWORD:?Error: REDIS_PASSWORD must be set in .env file}
    command: >
      redis-server
      --requirepass ${REDIS_PASSWORD:?Error: REDIS_PASSWORD must be set in .env file}
      --maxmemory 512mb
      --maxmemory-policy noeviction
      --save 900 1
      --save 300 10
      --appendonly yes
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD-SHELL", "redis-cli -a \"$$REDIS_PASSWORD\" ping | grep -q PONG"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  mariadb_data:
  redis_data:
```

### Setup & Launch
1. Copy the environment file template and configure strong secrets:
```bash
cp .env.example .env
# Edit .env and set strong passwords for DB_PASSWORD, MYSQL_ROOT_PASSWORD, and REDIS_PASSWORD
```
2. Launch the cluster:
```bash
docker compose up -d
```
3. Access Sparky LMS at `http://localhost` (HTTP) or `https://localhost` (HTTPS with self-signed SSL). For production certificates, mount your Let's Encrypt certificates into `docker/ssl/`.

---

## 5. Local Development Quickstart

### Windows Quickstart
1. Ensure **PHP 8.1+** and **MariaDB** are installed locally.
2. Double-click `start-server.bat` in the repository root.
3. The script automatically launches MariaDB and starts the development server at `http://localhost:8000`.

### Linux / macOS Quickstart
Run the built-in development router with media streaming support from repository root:
```bash
php -S localhost:8000 router.php
```

Visit `http://localhost:8000` in your web browser.

---

## 6. Configuration & Environment Variables

All core infrastructure variables are dynamically resolved in `app/config/configuration.php` (created from `app/config/configuration.php.dist`). You can configure them directly or set environment variables:

| Environment Variable | Default Value | Description |
| :--- | :--- | :--- |
| `DB_HOST` | `127.0.0.1` | MariaDB / MySQL host IP or hostname |
| `DB_PORT` | `3306` | Database port |
| `DB_NAME` | `sparky_lms` | Main database name |
| `DB_USER` | `sparky_user` | Database username |
| `DB_PASSWORD` | `""` | Database password |
| `APP_HOST` | `localhost:8000` | Host domain name for URL generation |
| `REDIS_HOST` | `127.0.0.1` | Redis server hostname |
| `REDIS_PORT` | `6379` | Redis server port |
| `REDIS_PASSWORD`| `""` | Redis authentication token |

---

## 7. Video Streaming & Partial Content (HTTP 206)

HTML5 video players require standard HTTP 206 Partial Content (Byte-Range) responses to support timeline scrubbing, jumping forward, and seeking through lecture videos without buffering the entire file.

### How it works:
1. When a student scrubs the timeline, the browser sends:
   ```http
   GET /courses/AI101/document/video.mp4 HTTP/1.1
   Range: bytes=1048576-2097151
   ```
2. The Sparky media engine intercepts the request and responds with:
   ```http
   HTTP/1.1 206 Partial Content
   Accept-Ranges: bytes
   Content-Range: bytes 1048576-2097151/157286400
   Content-Length: 1048576
   Content-Type: video/mp4
   ```
3. Nginx and the development `router.php` both support this protocol natively out of the box while enforcing student authorization.

---

## 8. Security & Production Hardening

- **Direct Web Access Blocks:** Direct requests to `/database/`, `/app/config/`, `/app/courses/`, `/.git`, and `/.env` return HTTP 403 Forbidden.
- **Course Authorization Enforcement:** All private course materials (videos, documents, assignments) route through LMS authentication handlers (`download.php`, `download_scorm.php`) requiring active student enrollment.
- **Upload Execution Protection:** Script execution (`.php`, `.phtml`, `.phar`) inside writable directories (`app/upload/`, `courses/`, `app/cache/`) is blocked at both web server and application levels.
- **XSS & SQL Injection Defense:** All user inputs pass through sanitized database abstractions (`Database::escape_string()`, PDO parameter binding, and `Security::remove_XSS()`).
- **Session Security:** Runtime enforcement of `session.cookie_httponly = 1`, `session.cookie_secure = 1`, `session.use_only_cookies = 1`, and `session.use_strict_mode = 1`.
- **CORS Boundary Protection:** Eliminates wildcard CORS on protected course media; restricts playback to same-origin or configured host.

---

## 9. Automated Verification & Testing

Sparky LMS includes an automated test runner validating database schemas, endpoints, security boundaries, and streaming engines.

Run the test suite:
```bash
php tests/run_test_suite.php
```

Expected output:
```text
========================================================
        SPARKY LMS - COMPREHENSIVE TEST SUITE           
========================================================
--- 1. PHP Runtime & Dependency Contract ---
  [PASS] PHP Version >= 8.1
  [PASS] Extension: cURL loaded
  [PASS] Extension: FileInfo loaded
  [PASS] Extension: GD loaded
  [PASS] Extension: Intl loaded
  [PASS] Extension: JSON loaded
  [PASS] Extension: Multibyte String (mbstring) loaded
  [PASS] Extension: XML loaded
  [PASS] Extension: Zip loaded

--- 2. Composer & Deployment Contract ---
  [PASS] composer.json exists
  [PASS] composer.json is valid JSON
  [PASS] composer.json declares PHP 8.1+ contract
  [PASS] composer.json pins platform PHP to 8.3
  [PASS] composer.json has security audit blocking enabled
  [PASS] composer.lock exists
  [PASS] composer.lock platform php matches 8.1+
  [PASS] app/config/configuration.php.dist exists
  [PASS] docker-compose.yml exists
  [PASS] docker-compose.yml has NO hardcoded fallback DB_PASSWORD (:-)
  [PASS] docker-compose.yml has NO hardcoded fallback REDIS_PASSWORD (:-)
  [PASS] docker-compose.yml has NO hardcoded fallback MYSQL_ROOT_PASSWORD (:-)
  [PASS] docker-compose.yml mandates DB_PASSWORD via :? error syntax
  [PASS] docker-compose.yml mandates MYSQL_ROOT_PASSWORD via :? error syntax
  [PASS] docker-compose.yml mandates REDIS_PASSWORD via :? error syntax
  [PASS] docker-compose.yml mounts SSL certificates directory
  [PASS] docker-compose.yml mounts sparky routes include
  [PASS] .env.example exists
  [PASS] .env.example documents DB_PASSWORD
  [PASS] .env.example documents MYSQL_ROOT_PASSWORD
  [PASS] .env.example documents REDIS_PASSWORD
  [PASS] .gitignore ignores .env
  [PASS] Dockerfile exists
  [PASS] Dockerfile copies composer.json and composer.lock
  [PASS] Dockerfile runs composer install
  [PASS] Dockerfile sets up entrypoint script
  [PASS] docker/entrypoint.sh exists
  [PASS] docker/entrypoint.sh validates vendor/autoload.php presence
  [PASS] docker/ssl/cert.pem exists
  [PASS] docker/ssl/key.pem exists
  [PASS] docker/ssl/cert.pem is non-empty
  [PASS] docker/ssl/key.pem is non-empty
  [PASS] docker/nginx.conf exists
  [PASS] docker/nginx.conf listens on HTTP (port 80)
  [PASS] docker/nginx.conf listens on HTTPS (port 443 ssl)
  [PASS] docker/nginx.conf references SSL cert and key
  [PASS] docker/sparky-routes.inc exists
  [PASS] docker/sparky-routes.inc blocks direct access to app/courses
  [PASS] docker/sparky-routes.inc routes courses through download handlers (ZERO aliases to disk)
  [PASS] nginx.conf.sample exists
  [PASS] nginx.conf.sample contains ZERO filesystem aliases into app/courses
  [PASS] nginx.conf.sample blocks direct access to app/courses
  [PASS] nginx.conf.sample routes course downloads via PHP handler

--- 3. Web Server Access Controls & Negative Security Tests ---
  [PASS] .htaccess exists
  [PASS] .htaccess blocks direct access to database/ directory
  [PASS] .htaccess blocks .sql dump files
  [PASS] .htaccess blocks .bat scripts
  [PASS] .htaccess blocks .sh shell scripts
  [PASS] .htaccess sets X-Content-Type-Options nosniff
  [PASS] .htaccess sets X-Frame-Options SAMEORIGIN
  [PASS] .htaccess blocks direct access to app/courses
  [PASS] .htaccess contains no direct disk rewrite to app/courses
  [PASS] database/.htaccess exists (defense-in-depth)
  [PASS] database/.htaccess denies all access
  [PASS] web.config exists
  [PASS] web.config blocks database and sensitive files
  [PASS] router.php exists
  [PASS] router.php blocks /database directory
  [PASS] router.php blocks /app/config directory
  [PASS] router.php blocks direct /app/courses directory access
  [PASS] router.php blocks .sql extension
  [PASS] router.php blocks .bat extension
  [PASS] router.php avoids wildcard CORS on media

--- 4. Curriculum Upload & RCE Prevention Tests ---
  [PASS] Curriculum upload strictly rejects .php files
  [PASS] Curriculum upload strictly rejects .phar archives
  [PASS] Curriculum upload strictly rejects .sh shell scripts
  [PASS] Curriculum upload strictly rejects .exe executables
  [PASS] detectLessonTypeFromFilename identifies MP4 as video
  [PASS] detectLessonTypeFromFilename identifies WEBM as video
  [PASS] detectLessonTypeFromFilename identifies PDF as pdf
  [PASS] detectLessonTypeFromFilename identifies MP3 as audio
  [PASS] detectLessonTypeFromFilename identifies DOCX as document

--- 5. Certificate Blueprints & XSS Sanitization Tests ---
  [PASS] Certificate rendering escapes XSS script tag in student name
  [PASS] Certificate Editor background upload disallows SVG to prevent Stored XSS

--- 6. HTTP 206 Partial Content Range Parsing Tests ---
  [PASS] Range bytes=0-1023 parses start 0, end 1023, length 1024
  [PASS] Open range bytes=500000- parses start 500000, end 999999
  [PASS] Suffix range bytes=-500 parses start 999500, end 999999
  [PASS] Out-of-bounds range returns HTTP 416 Range Not Satisfiable

--- 7. Runtime Session Security Contract ---
  [PASS] chamilo_session enforces session.cookie_httponly = 1
  [PASS] chamilo_session enforces session.use_only_cookies = 1
  [PASS] chamilo_session enforces session.use_strict_mode = 1

--- 8. Database Schema & Bootstrap Verification ---
  [PASS] Clean database dump exists (database/sparky_clean_database.sql)
  [PASS] Database dump has valid MariaDB / MySQL dump header
  [PASS] Database contains table definition: `user`
  [PASS] Database contains table definition: `course`
  [PASS] Database contains table definition: `session`
  [PASS] Database contains table definition: `c_document`
  [PASS] Database contains table definition: `c_item_property`
  [PASS] Database contains table definition: `c_certificate_blueprint`
  [PASS] Database contains table definition: `c_curriculum_chapter`
  [PASS] Database contains table definition: `c_curriculum_lesson`
  [PASS] Database contains table definition: `user_magic_token`

--- 9. Router Multi-Pass Traversal & Canonical Path Security ---
  [PASS] Multi-pass decode detects double-encoded traversal (%252e%252e)
  [PASS] Multi-pass decode detects triple-encoded traversal (%25252e%25252e)
  [PASS] Null byte \0 detected in URI
  [PASS] URL-encoded null byte %00 decodes to \0
  [PASS] router.php course rewrites are defined BEFORE generic media streaming
  [PASS] router.php implements assertSafePath() function

--- 10. Redis Session Architecture & Eviction Protection ---
  [PASS] configuration.php.dist isolates sessions to redis_session_db (database 0)
  [PASS] configuration.php.dist isolates cache to redis_cache_db (database 1)
  [PASS] configuration.php.dist uses dedicated session prefix sparky_sess:
  [PASS] docker-compose.yml configures --maxmemory-policy noeviction
  [PASS] docker-compose.yml configures Redis RDB snapshots (--save 900 1)
  [PASS] docker-compose.yml configures Redis Append-Only File (--appendonly yes)

--- 11. Cryptographic Entropy & Magic Login Security ---
  [PASS] random_bytes(32) produces 64-char hexadecimal string (256 bits entropy)
  [PASS] 1,000 generated tokens exhibit zero collisions (collision-resistant)
  [PASS] MagicLoginManager uses cryptographically secure random_bytes

--- 12. Course Curriculum RBAC & Authorization Checks ---
  [PASS] curriculum.ajax.php rejects unauthenticated requests with HTTP 401
  [PASS] curriculum.ajax.php validates write permissions via canEdit check
  [PASS] curriculum.ajax.php rejects unauthorized writes with HTTP 403

--- 13. Certificate Template Upload & XSS Hardening ---
  [PASS] certificate_editor.php restricts uploads to safe image types (jpg, jpeg, png, webp, gif)
  [PASS] certificate_editor.php generates cryptographically randomized background filenames

========================================================
PASSED:   121
FAILED:   0
WARNINGS: 0
========================================================
ALL TESTS PASSED SUCCESSFULLY.
```

---

## 10. Documentation Index

Comprehensive engineering and security documentation is available in the [`documentation/`](documentation/) directory:

- 📘 [Engineering Audit & Architecture Inventory](documentation/ENGINEERING_AUDIT.md) — Comprehensive breakdown of core Chamilo foundation vs. custom Sparky subsystems.
- 🛡️ [Security Audit & Vulnerability Remediation](documentation/SECURITY_AUDIT.md) — Analysis of threats, remediation implementations, and secret scanning resolution.
- 🚀 [Production & Local Deployment Guide](documentation/DEPLOYMENT_GUIDE.md) — Verified setup instructions for Docker Compose, Ubuntu/Debian Linux, and local Windows development.
- 🧪 [Automated Verification & Test Report](documentation/TEST_REPORT.md) — Detailed results of the 121-assertion automated verification suite across all 13 categories.

---

## 11. Troubleshooting & FAQ

#### Q: "Database is unavailable" error on first launch
- **Cause:** MariaDB service is stopped or database credentials do not match.
- **Fix:** Check service status with `sudo systemctl status mariadb` and verify database credentials in `/etc/environment` or `app/config/configuration.php`.

#### Q: Video player cannot seek or scrub forward
- **Cause:** The web server is serving the video as a monolithic static download without `Accept-Ranges: bytes` headers.
- **Fix:** Ensure the Nginx configuration includes `add_header Accept-Ranges bytes;` and `proxy_set_header Range $http_range;` as documented in [Step 6](#step-6-configure-nginx-with-http-206-streaming). For local testing, always launch with `router.php`.

#### Q: Redis session errors (`Connection refused`)
- **Cause:** Redis server is not listening on `127.0.0.1:6379`.
- **Fix:** Run `redis-cli ping`. If it fails, start Redis using `sudo systemctl restart redis-server`.

#### Q: Uploading large videos or SCORM packages fails
- **Cause:** PHP or Nginx upload limits exceeded.
- **Fix:** Verify `upload_max_filesize = 500M` and `post_max_size = 500M` in `php.ini`, and ensure `client_max_body_size 500M;` is present in your Nginx configuration.

---

## License

Sparky LMS is licensed under the [GNU General Public License v3 (GPLv3)](license.txt).
