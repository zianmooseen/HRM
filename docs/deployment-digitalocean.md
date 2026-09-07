# DigitalOcean Droplet Deployment

This project deploys from GitHub Actions to a DigitalOcean Droplet over SSH.

The pipeline runs when code is pushed to `main`:

1. Installs and builds the Nuxt frontend.
2. Installs and tests the Laravel backend against PostgreSQL.
3. Syncs the repository to the droplet with `rsync`.
4. Runs `scripts/deploy-production.sh` on the droplet.

## Droplet Prerequisites

Install the runtime stack on the droplet:

```bash
sudo apt update
sudo apt install -y nginx postgresql postgresql-contrib rsync unzip git curl
```

Install PHP, Composer, Node.js, and npm versions compatible with the app:

```bash
php -v
composer --version
node -v
npm -v
```

The app currently expects PHP `8.5` and Node.js `22`.

## Application Directory

Create the deploy directory:

```bash
sudo mkdir -p /var/www/uae-hrm
sudo chown -R deploy:deploy /var/www/uae-hrm
```

Replace `deploy` with your droplet deploy user.

## Environment Files

Create these files directly on the droplet. They are intentionally excluded from GitHub sync.

```bash
nano /var/www/uae-hrm/backend/.env
nano /var/www/uae-hrm/frontend/.env
```

Frontend:

```env
NUXT_PUBLIC_API_BASE_URL=https://api.example.com/api
```

Backend values must use production credentials:

```env
APP_NAME="UAE HRM Platform"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=uae_hrm
DB_USERNAME=uae_hrm
DB_PASSWORD=replace-with-a-secure-password

FRONTEND_URL=https://app.example.com
SANCTUM_STATEFUL_DOMAINS=app.example.com
SESSION_DOMAIN=.example.com
```

Generate the backend key once:

```bash
cd /var/www/uae-hrm/backend
php artisan key:generate
```

## Nuxt Systemd Service

Create `/etc/systemd/system/hrm-frontend.service`:

```ini
[Unit]
Description=UAE HRM Nuxt frontend
After=network.target

[Service]
Type=simple
User=deploy
WorkingDirectory=/var/www/uae-hrm/frontend
Environment=HOST=127.0.0.1
Environment=PORT=3000
ExecStart=/usr/bin/node .output/server/index.mjs
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

Enable it:

```bash
sudo systemctl daemon-reload
sudo systemctl enable hrm-frontend
```

## GitHub Secrets

Add these in GitHub under **Repository > Settings > Secrets and variables > Actions**:

```txt
DROPLET_HOST=your.droplet.ip.address
DROPLET_USER=deploy
DROPLET_PORT=22
DROPLET_SSH_KEY=your private SSH deploy key
DEPLOY_PATH=/var/www/uae-hrm
DEPLOY_FRONTEND_SERVICE=hrm-frontend
DEPLOY_PHP_FPM_SERVICE=php8.5-fpm
```

`DROPLET_SSH_KEY` should be the private key matching a public key in `/home/deploy/.ssh/authorized_keys` on the droplet.

## Required Sudo Access

The deploy script restarts the Nuxt service, PHP-FPM, and reloads Nginx. For a non-root deploy user, allow only those service commands:

```bash
sudo visudo
```

Example:

```txt
deploy ALL=(root) NOPASSWD: /bin/systemctl restart hrm-frontend, /bin/systemctl restart php8.5-fpm, /bin/systemctl reload nginx
```

Adjust the PHP-FPM service name if your server uses a different PHP version.

## First Deployment

After the droplet is prepared and GitHub secrets are saved, push to `main`:

```bash
git push origin main
```

You can also run the workflow manually from the GitHub Actions tab.
