#!/usr/bin/env bash
set -Eeuo pipefail

APP_ROOT="${APP_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
BACKEND_DIR="$APP_ROOT/backend"
FRONTEND_DIR="$APP_ROOT/frontend"

log() {
  printf '\n[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

run_systemctl() {
  local action="$1"
  local service="$2"

  if [[ -z "$service" ]]; then
    return 0
  fi

  if ! command -v systemctl >/dev/null 2>&1; then
    echo "systemctl is unavailable; cannot $action $service." >&2
    return 1
  fi

  # Test the exact service command, so a narrow sudoers rule is sufficient.
  if command -v sudo >/dev/null 2>&1 && sudo -n systemctl "$action" "$service"; then
    return 0
  fi
  if systemctl --no-ask-password "$action" "$service"; then
    return 0
  fi
  echo "Could not $action $service. Grant the deploy account passwordless sudo for this specific systemctl command." >&2
  return 1
}

require_file() {
  local path="$1"

  if [[ ! -f "$path" ]]; then
    echo "Missing required file: $path" >&2
    exit 1
  fi
}

require_file "$BACKEND_DIR/.env"
require_file "$FRONTEND_DIR/.env"

log "Installing Laravel production dependencies"
cd "$BACKEND_DIR"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

log "Preparing Laravel storage directories"
mkdir -p \
  storage/app \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

php artisan config:clear
php artisan cache:preflight

if [[ "${DEPLOY_RESET_TEST_DATA:-false}" == "true" ]]; then
  log "Archiving and resetting disposable hosted test data"
  php artisan down
  php artisan clients:prepare-provisioner --force
  php artisan test-data:reset --force --confirm=RESET_DISPOSABLE_HOSTED_TEST_DATA
else
  log "Checking database migration blockers"
  php artisan schema:preflight
  log "Running Laravel database migrations"
  php artisan down
  php artisan migrate --force
  php artisan clients:upgrade --force
fi
php artisan clients:sync-logins

log "Refreshing Laravel caches"
php artisan optimize:clear
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

cd "$FRONTEND_DIR"

if [[ -f ".output/server/index.mjs" ]]; then
  log "Using Nuxt artifact built by GitHub Actions"
else
  log "Nuxt artifact is missing; building on droplet"
  npm ci
  npm run build
fi

log "Restarting services"
run_systemctl restart "${DEPLOY_FRONTEND_SERVICE:-hrm-frontend}"
run_systemctl restart "${DEPLOY_PHP_FPM_SERVICE:-php8.5-fpm}"
run_systemctl reload "${DEPLOY_NGINX_SERVICE:-nginx}"

cd "$BACKEND_DIR"
php artisan up

log "Deployment complete"
