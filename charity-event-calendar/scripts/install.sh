#!/usr/bin/env bash
# ==============================================================================
# Charity Event Calendar - EC2 User Data & Automated Provisioning Script
# Target OS: Amazon Linux 2023 / Amazon Linux 2 / Ubuntu 22.04 LTS
# Compatible with AWS Auto Scaling Group Launch Templates
# ==============================================================================

set -euo pipefail

echo ">>> [1/6] Initializing System Packages and Dependencies..."

# Detect Linux Distribution
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
else
    OS="unknown"
fi

if [[ "$OS" == "amzn" || "$OS" == "fedora" || "$OS" == "rhel" ]]; then
    # Amazon Linux 2023 / AL2
    dnf update -y || yum update -y
    dnf install -y httpd git unzip curl jq php php-cli php-mysqlnd php-pdo php-json php-mbstring php-xml php-curl || \
        amazon-linux-extras install -y php8.0 && yum install -y httpd git jq
    
    SYSTEM_USER="apache"
    WEB_ROOT="/var/www/html"
    SERVICE_NAME="httpd"
elif [[ "$OS" == "ubuntu" || "$OS" == "debian" ]]; then
    # Ubuntu / Debian
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y
    apt-get install -y apache2 git unzip curl jq php php-cli php-mysql php-curl php-json php-mbstring php-xml
    
    SYSTEM_USER="www-data"
    WEB_ROOT="/var/www/html"
    SERVICE_NAME="apache2"
    a2enmod rewrite headers
else
    echo "Unsupported OS: $OS"
    exit 1
fi

echo ">>> [2/6] Configuring Apache Web Server..."
# Ensure AllowOverride All is enabled for .htaccess
cat << 'EOF' > /etc/httpd/conf.d/charity.conf 2>/dev/null || cat << 'EOF' > /etc/apache2/conf-available/charity.conf 2>/dev/null || true
<Directory "/var/www/html">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
EOF

if [ -f /etc/apache2/conf-available/charity.conf ]; then
    a2enconf charity || true
fi

echo ">>> [3/6] Deploying Application Code..."
# Target deployment directory
APP_DIR="/var/www/html"

# If deploying from Git repository, replace the URL below:
REPO_URL="https://github.com/your-username/charity-event-calendar.git"

if [ ! -f "$APP_DIR/health.php" ]; then
    # If app is packaged locally or in an S3 deployment bucket:
    # aws s3 cp s3://my-charity-app-bucket/charity-app.zip /tmp/app.zip && unzip /tmp/app.zip -d $APP_DIR
    echo "Directory ready for code synchronization."
fi

echo ">>> [4/6] Configuring Environment & AWS Secrets Manager Integration..."
cat << 'EOF' > "$APP_DIR/.env"
APP_NAME="Charity Event Calendar"
APP_ENV=production
APP_DEBUG=false
USE_AWS_SECRETS=true
AWS_REGION=us-east-1
AWS_SECRETS_NAME=charity-calendar/rds-credentials
SESSION_SECURE_COOKIE=false
EOF

echo ">>> [5/6] Applying File Permissions & Security Hardening..."
chown -R "$SYSTEM_USER:$SYSTEM_USER" "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;

# Restrict .env
chmod 600 "$APP_DIR/.env" || true

echo ">>> [6/6] Starting & Enabling Apache Web Server..."
systemctl enable "$SERVICE_NAME"
systemctl restart "$SERVICE_NAME"

echo ">>> Deployment completed successfully! Testing local health endpoint:"
curl -s -o /dev/null -w "%{http_code}\n" http://localhost/health.php || true
