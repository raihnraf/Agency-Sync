#!/bin/bash
# deploy.sh - Server-side deployment script for AgencySync

set -e  # Exit on error
set -u  # Exit on undefined variable
set -o pipefail  # Exit on pipe failure

APP_DIR="/var/www/agency-sync"
BACKUP_DIR="/var/backups/agency-sync"
LOG_FILE="/var/log/agency-sync/deploy.log"

# Log function
log() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Create backup directory
mkdir -p "$BACKUP_DIR"
mkdir -p "$(dirname "$LOG_FILE")"

log "Starting deployment..."

# Navigate to app directory
cd "$APP_DIR" || exit 1

# Backup current version before deployment
log "Creating backup..."
tar -czf "$BACKUP_DIR/backup-$(date +%Y%m%d-%H%M%S).tar.gz" \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/views/*' \
    .

log "Pulling latest code..."
if git pull origin main; then
    log "Code pull successful"
else
    log "ERROR: Git pull failed"
    exit 1
fi

log "Installing dependencies..."
if composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev; then
    log "Dependencies installed successfully"
else
    log "ERROR: Composer install failed"
    exit 1
fi

log "Clearing Laravel caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

log "Running database migrations..."
if php artisan migrate --force; then
    log "Migrations completed successfully"
else
    log "ERROR: Migrations failed"
    log "Restoring from backup..."
    # Rollback logic would go here
    exit 1
fi

log "Restarting Docker containers..."
docker compose down
docker compose up -d --build

log "Waiting for application to start..."
sleep 15

log "Running health check..."
if curl -f http://localhost/health; then
    log "Health check passed"
else
    log "ERROR: Health check failed"
    exit 1
fi

log "Deployment successful!"
exit 0
