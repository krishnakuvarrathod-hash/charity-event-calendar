#!/usr/bin/env bash
# ==============================================================================
# Charity Event Calendar - EC2 Continuous Deployment / Code Sync Script
# ==============================================================================

set -euo pipefail

APP_DIR="/var/www/html"
BRANCH="${1:-main}"

echo "=========================================================="
echo " Deploying Charity Event Calendar updates to $APP_DIR..."
echo "=========================================================="

cd "$APP_DIR"

# 1. Fetch latest commits if git is configured
if [ -d ".git" ]; then
    echo ">>> Pulling latest changes from Git ($BRANCH)..."
    git fetch origin
    git checkout "$BRANCH"
    git pull origin "$BRANCH"
else
    echo ">>> Git not initialized in $APP_DIR. Syncing via package/archive..."
fi

# 2. Reset permissions
echo ">>> Enforcing strict permissions..."
if id "apache" &>/dev/null; then
    chown -R apache:apache "$APP_DIR"
elif id "www-data" &>/dev/null; then
    chown -R www-data:www-data "$APP_DIR"
fi

find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod 600 "$APP_DIR/.env" 2>/dev/null || true

# 3. Reload Apache without dropping active TCP connections
echo ">>> Reloading Web Server gracefully..."
if systemctl is-active --quiet httpd; then
    systemctl reload httpd
elif systemctl is-active --quiet apache2; then
    systemctl reload apache2
fi

# 4. Execute automated health check
echo ">>> Probing Health Endpoint..."
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/health.php || echo "000")

if [ "$HTTP_STATUS" -eq 200 ]; then
    echo ">>> SUCCESS: Health endpoint responded HTTP 200 (Healthy)!"
else
    echo ">>> WARNING: Health endpoint responded with status $HTTP_STATUS."
    echo ">>> Run 'journalctl -xeu httpd' or inspect database logs."
fi

echo "=========================================================="
echo " Deployment sequence concluded."
echo "=========================================================="
