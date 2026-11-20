# Sparky LMS — Production & Local Deployment Guide

**Target Environment:** PHP 8.1 - 8.3 | MariaDB 10.6+ | Redis 7.x | Nginx 1.24+  
**Tested On:** Ubuntu 22.04/24.04 LTS, Debian 12, Windows 10/11 Local Dev, and Docker Compose  

---

## 1. System Requirements

| Component | Minimum Specification | Recommended Production |
|---|---|---|
| **CPU** | 2 vCPU | 4+ vCPU |
| **RAM** | 4 GB | 8 GB+ (16 GB for >500 concurrent video streams) |
| **Storage** | 20 GB SSD | 100+ GB NVMe SSD |
| **Operating System** | Linux (Ubuntu/Debian) | Ubuntu 24.04 LTS / Debian 12 |
| **PHP Runtime** | PHP 8.1 | **PHP 8.3** (`fpm` + `cli`) |
| **Database** | MariaDB 10.6 / MySQL 8.0 | **MariaDB 10.11 LTS** |
| **Session & Cache** | Redis 6.0 | **Redis 7.2** |
| **Web Server** | Nginx 1.20 | **Nginx 1.24+** |

---

## 2. Docker Compose Deployment (Recommended)

Docker Compose provides a reproducible, isolated, and production-ready deployment containing PHP 8.3 FPM, Nginx with TLS, MariaDB 10.6, and Redis 7.2.

### Step 1: Clone Repository
```bash
git clone https://github.com/vinaysinbaghel/Sparky-lms-.git /opt/sparky-lms
cd /opt/sparky-lms
```

### Step 2: Configure Environment
Copy `.env.example` to `.env` and configure secure, unique passwords:
```bash
cp .env.example .env
chmod 600 .env
```

Ensure the following mandatory keys are defined in `.env`:
```env
APP_ENV=production
APP_HOST=lms.yourdomain.com
APP_URL=https://lms.yourdomain.com

DB_HOST=db
DB_PORT=3306
DB_NAME=sparky_lms
DB_USER=sparky_user
DB_PASSWORD=YourStrongDatabasePassword2026!
MYSQL_ROOT_PASSWORD=YourStrongRootPassword2026!

REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=YourStrongRedisPassword2026!
REDIS_SESSION_DB=0
REDIS_CACHE_DB=1

SECURITY_KEY=Your32CharRandomProductionSecurityKey!
```

### Step 3: SSL Certificates
For development or staging, default self-signed certificates exist in `docker/ssl/`.  
For production, replace `docker/ssl/cert.pem` and `docker/ssl/key.pem` with your valid certificate and private key:
```bash
cp /etc/letsencrypt/live/lms.yourdomain.com/fullchain.pem docker/ssl/cert.pem
cp /etc/letsencrypt/live/lms.yourdomain.com/privkey.pem docker/ssl/key.pem
chmod 600 docker/ssl/key.pem
```

### Step 4: Launch Containers
```bash
docker compose up -d --build
```

### Step 5: Verify Deployment
Check that all containers report healthy:
```bash
docker compose ps
```
Run the automated verification suite inside the container:
```bash
docker compose exec app php tests/run_test_suite.php
```

---

## 3. Native Linux Production Installation (Ubuntu 24.04 / Debian 12)

### Step 1: Install Dependencies
```bash
sudo apt update && sudo apt install -y software-properties-common curl git unzip
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update

# Install PHP 8.3 and extensions
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis \
    php8.3-curl php8.3-gd php8.3-intl php8.3-mbstring php8.3-xml php8.3-zip

# Install Web Server, DB, and Redis
sudo apt install -y nginx mariadb-server redis-server
```

### Step 2: Configure Redis
Edit `/etc/redis/redis.conf`:
```ini
requirepass YourStrongRedisPassword2026!
maxmemory 512mb
maxmemory-policy noeviction
save 900 1
save 300 10
appendonly yes
```
Restart Redis:
```bash
sudo systemctl restart redis-server
sudo systemctl enable redis-server
redis-cli -a YourStrongRedisPassword2026! ping
# Output should be: PONG
```

### Step 3: Configure MariaDB
```bash
sudo mysql -u root -e "CREATE DATABASE sparky_lms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -u root -e "CREATE USER 'sparky_user'@'localhost' IDENTIFIED BY 'YourStrongDatabasePassword2026!';"
sudo mysql -u root -e "GRANT ALL PRIVILEGES ON sparky_lms.* TO 'sparky_user'@'localhost';"
sudo mysql -u root -e "FLUSH PRIVILEGES;"

# Import initial database schema
sudo mysql -u sparky_user -pYourStrongDatabasePassword2026! sparky_lms < database/sparky_clean_database.sql
```

### Step 4: Deploy Code & Set Permissions
```bash
sudo git clone https://github.com/vinaysinbaghel/Sparky-lms-.git /var/www/sparky-lms
cd /var/www/sparky-lms

# Configure configuration.php
cp app/config/configuration.php.dist app/config/configuration.php

# Set file permissions
sudo chown -R www-data:www-data /var/www/sparky-lms
sudo find /var/www/sparky-lms -type d -exec chmod 755 {} \;
sudo find /var/www/sparky-lms -type f -exec chmod 644 {} \;

# Writable directories
sudo chmod -R 775 app/courses app/upload app/cache app/logs
```

### Step 5: Configure Nginx
Copy `nginx.conf.sample` to your Nginx sites directory:
```bash
sudo cp nginx.conf.sample /etc/nginx/sites-available/sparky-lms.conf
sudo ln -s /etc/nginx/sites-available/sparky-lms.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### Step 6: Scheduled Background Tasks (Cron)
Configure background jobs for user notifications and session maintenance:
```bash
sudo crontab -u www-data -e
```
Add:
```cron
# Send queued email notifications every 10 minutes
*/10 * * * * /usr/bin/php /var/www/sparky-lms/main/cron/send_notifications.php > /dev/null 2>&1
```

---

## 4. Local Development Setup (Windows / macOS)

### Step 1: Install PHP 8.3 CLI
Ensure PHP 8.3+ is installed and present in your system PATH with extensions:
`curl, fileinfo, gd, intl, json, mbstring, openssl, pdo, xml, zip`.

### Step 2: Validate Environment
Run the test suite from the repository root:
```bash
php tests/run_test_suite.php
```
All 135 tests must output `[PASS]`.

### Step 3: Run Development Server
Start the built-in development server using `router.php`:
```bash
php -S 127.0.0.1:8000 router.php
```
Open your browser and navigate to: `http://127.0.0.1:8000`
