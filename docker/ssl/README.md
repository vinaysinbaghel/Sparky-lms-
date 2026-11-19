# Sparky LMS - Docker SSL Certificates

This directory provides default SSL/TLS certificates for the Sparky LMS Docker Nginx gateway (port 443).

## Development / Localhost
Default self-signed certificates (`cert.pem` and `key.pem`) are included so that the HTTPS service starts immediately out-of-the-box without manual generation.

## Production Deployment
For production, replace these files with your trusted SSL certificates or mount your certificate authority / Let's Encrypt certificates:

1. **Custom Certificates**:
   - Place your full-chain certificate at `docker/ssl/cert.pem`
   - Place your private key at `docker/ssl/key.pem`

2. **Generating New Self-Signed Certificates**:
   ```bash
   openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
     -keyout docker/ssl/key.pem \
     -out docker/ssl/cert.pem \
     -subj "/C=US/ST=State/L=City/O=Organization/CN=yourdomain.com"
   ```

3. **Using Let's Encrypt (Certbot)**:
   Mount your Certbot certificates into the web container in `docker-compose.yml`:
   ```yaml
   volumes:
     - /etc/letsencrypt/live/yourdomain.com/fullchain.pem:/etc/nginx/ssl/cert.pem:ro
     - /etc/letsencrypt/live/yourdomain.com/privkey.pem:/etc/nginx/ssl/key.pem:ro
   ```
