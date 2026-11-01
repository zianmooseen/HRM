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


## Client database provisioning

Run `php artisan migrate --force` for the platform schema. New clients created through **Clients & companies → Add client** get a dedicated database and initial company administrator. The configured database server must permit database creation; provider snapshots alone do not grant that privilege. Use a shared locking cache and stable session storage for multiple app instances.

The deploy script runs `schema:preflight` before entering maintenance, so populated legacy tables fail without taking a running application offline. It then migrates the platform and runs `clients:upgrade --force` to pause/migrate/verify ready client databases. It rebuilds the sign-in lookup with `clients:sync-logins` before bringing the application up. Clients already paused remain paused. Drain external jobs/integrations before deployment. A failure after maintenance begins leaves the app down for inspection; it does not automatically resume partially upgraded clients. The schema-separation migration refuses populated legacy central HR tables or populated client billing tables. Back up and resolve those records explicitly before deployment. Configure server backups/PITR and file versioning separately; no backup service is activated by this code change. Follow [client database operations and restore runbook](client-databases.md) before enabling client creation in production.

## Reset disposable hosted test data

Only use this when all hosted client data may be replaced. In **Actions → Deploy to DigitalOcean Droplet → Run workflow**, select `main` and enable `reset_test_data`. Ordinary pushes and manual deployments with the option off never reset data. The workflow verifies the code, enters maintenance, then runs:

```bash
php artisan test-data:reset --force --confirm=RESET_DISPOSABLE_HOSTED_TEST_DATA
```

This PostgreSQL-only operation renames the entire existing `public` schema to `hrm_test_backup_<timestamp>_<random>`, revokes PUBLIC access to that schema, and creates a fresh platform schema. It preserves existing client databases and stored files. This is an in-database recovery archive, not an independent backup: take a server snapshot/backup separately for protection against server or disk loss. The application database role must own `public` and be able to create a schema; the provisioning role needs `CREATEDB` and permission to assign ownership to the client runtime role.

During this explicit reset only, deployment runs `clients:prepare-provisioner --force` while in maintenance. If the configured PostgreSQL role lacks database creation permission and uses a loopback host, it attempts `sudo -n -u postgres psql` to grant that role only `CREATEDB`. It does not grant superuser/role-management privileges or change passwords. An existing provisioner with `CREATEDB` needs no sudo access. A remote database or unavailable administrator access stops before the schema is archived and prints the exact SQL for a server administrator to run. Ordinary deployments never run this setup command. Separate provisioning/runtime connection credentials remain supported through `TENANT_PROVISIONING_CONNECTION` and `TENANT_TEMPLATE_CONNECTION`.

The reset preserves the first active global platform administrator's ID and password, clears their remember token, and replaces other users with test accounts. It creates Demo, Retail, and Logistics clients, each in its own database with 160 employees and synthetic attendance, leave, payroll, WPS, onboarding, documents, and temporary-worker data. New company/client IDs exceed the old IDs so old client sessions and file paths cannot select new data.

All new test users share a newly generated 40-character password. The workflow prints only the private credentials file path, never the password. Read that file directly on the server: `backend/storage/app/private/hosted-test-resets/hrm_test_backup_*.json` (mode `0600`, directory `0700`). It records the archive name, previous client databases, test usernames, and password. If no global administrator existed, it also contains the generated `sys.admin` password. Remove or rotate these test accounts before using the system for real clients.

Failures leave maintenance enabled and preserve the archive. Inspect the private recovery file and Laravel logs before retrying. For rollback, stop jobs and requests, retain the failed new schema under another name, rename the recorded archive back to `public` in a transaction, deploy its matching application revision, and verify it before running `php artisan up`. Do not drop old schemas/databases until recovery is no longer required.
