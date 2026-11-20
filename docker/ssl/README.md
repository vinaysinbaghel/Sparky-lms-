# Sparky LMS — Docker SSL/TLS Certificates

This directory is the mount point for the Sparky LMS Nginx gateway SSL/TLS certificates (port 443).

> [!WARNING]
> **No private keys are committed to this repository.**  
> If an earlier repository commit (`75c9105`) containing a test `key.pem` was ever deployed to any publicly accessible host, that private key is considered compromised and **must be immediately rotated**.

---

## 1. Local Development Setup

To generate a local development certificate and private key, run:

```bash
# On Linux / macOS:
bash docker/ssl/generate-certs.sh

# On Windows:
docker\ssl\generate-certs.bat
```

This creates:
- `docker/ssl/cert.pem` (Self-signed X.509 certificate)
- `docker/ssl/key.pem` (Private key, ignored by `.gitignore`)

---

## 2. Production Deployment

For production deployments, do not use self-signed certificates. Use a trusted certificate authority (CA) or Let's Encrypt:

### Option A: Place Certificates in `docker/ssl/`
1. Copy your full-chain certificate to `docker/ssl/cert.pem`.
2. Copy your private key to `docker/ssl/key.pem` (mode `0600`).
3. Ensure both files are not committed to git (enforced by `.gitignore`).

### Option B: Mount Host Certificates (e.g. Let's Encrypt / Certbot)
In `docker-compose.yml`, update the `web` service volume mounts:
```yaml
volumes:
  - /etc/letsencrypt/live/yourdomain.com/fullchain.pem:/etc/nginx/ssl/cert.pem:ro
  - /etc/letsencrypt/live/yourdomain.com/privkey.pem:/etc/nginx/ssl/key.pem:ro
```
