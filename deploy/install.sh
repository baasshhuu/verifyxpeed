#!/usr/bin/env bash
# VerifyXpeed one-shot installer for an Ubuntu VPS (Hostinger KVM).
# Usage:  sudo bash deploy/install.sh auto admin@email.com            (free link: verifyxpeed.<server-ip>.sslip.io)
#         sudo bash deploy/install.sh verify.yourdomain.com admin@email.com ['AdminPassword']
set -euo pipefail
DOMAIN="${1:-auto}"
ADMIN_EMAIL="${2:?admin email needed}"
ADMIN_PASS="${3:-$(openssl rand -base64 12 | tr -d '/+=' | cut -c1-12)}"
APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
say(){ echo -e "\n\033[1;32m==> $*\033[0m"; }
if [ "$DOMAIN" = "auto" ]; then
  IP=$(curl -s -4 https://api.ipify.org || hostname -I | awk '{print $1}')
  DOMAIN="verifyxpeed.${IP//./-}.sslip.io"
fi
say "Installing VerifyXpeed at ${DOMAIN}"

say "Checking PHP and Composer"
command -v php >/dev/null || { apt-get update && apt-get install -y php-cli php-fpm php-mysql php-sqlite3 php-mbstring php-xml php-curl php-zip php-bcmath php-intl unzip; }
PHPV=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
php -r 'exit(version_compare(PHP_VERSION,"8.2.0",">=")?0:1);' || { echo "PHP 8.2+ needed (found $PHPV)"; exit 1; }
for ext in mbstring xml curl sqlite3 mysql intl zip bcmath; do php -m | grep -qi "^${ext/mysql/pdo_mysql}$" || apt-get install -y "php${PHPV}-${ext}" || true; done
command -v composer >/dev/null || { curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer; }
command -v supervisorctl >/dev/null || apt-get install -y supervisor

say "Installing PHP packages"
cd "$APP_DIR"
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs storage/app bootstrap/cache
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction

say "Setting up .env"
[ -f .env ] || cp .env.example .env
sed -i "s#^APP_URL=.*#APP_URL=https://${DOMAIN}#" .env
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force

if command -v mysql >/dev/null && mysql -e 'select 1' >/dev/null 2>&1; then
  say "Creating MySQL database"
  DBPASS=$(openssl rand -hex 16)
  mysql -e "CREATE DATABASE IF NOT EXISTS verifyxpeed CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'verifyxpeed'@'localhost' IDENTIFIED BY '${DBPASS}'; ALTER USER 'verifyxpeed'@'localhost' IDENTIFIED BY '${DBPASS}'; GRANT ALL ON verifyxpeed.* TO 'verifyxpeed'@'localhost'; FLUSH PRIVILEGES;"
  sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/" .env
  sed -i "s/^# *DB_HOST=.*/DB_HOST=127.0.0.1/; s/^# *DB_PORT=.*/DB_PORT=3306/; s/^# *DB_DATABASE=.*/DB_DATABASE=verifyxpeed/; s/^# *DB_USERNAME=.*/DB_USERNAME=verifyxpeed/; s/^# *DB_PASSWORD=.*/DB_PASSWORD=${DBPASS}/" .env
  sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DBPASS}/" .env
else
  say "MySQL not found, using SQLite (fine to start; switch later)"
  touch database/database.sqlite
fi

say "Database tables + admin login"
php artisan migrate --force
php artisan vx:make-admin "$ADMIN_EMAIL" "$ADMIN_PASS"
php artisan storage:link >/dev/null 2>&1 || true
php artisan config:cache && php artisan route:cache && php artisan view:cache
chown -R www-data:www-data storage bootstrap/cache database
chmod -R ug+rw storage bootstrap/cache database

SOCK=$(ls /run/php/php${PHPV}-fpm.sock 2>/dev/null || ls /run/php/php*-fpm.sock 2>/dev/null | head -1 || true)
if systemctl is-active --quiet apache2 2>/dev/null; then
  WEB=apache
  say "Apache site for ${DOMAIN}"
  a2enmod rewrite headers >/dev/null
  if [ -n "$SOCK" ]; then a2enmod proxy_fcgi setenvif >/dev/null; PHPH="<FilesMatch \.php$>
        SetHandler \"proxy:unix:${SOCK}|fcgi://localhost\"
    </FilesMatch>"; else PHPH=""; fi
  cat > /etc/apache2/sites-available/verifyxpeed.conf <<APACHE
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot ${APP_DIR}/public
    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
    </Directory>
    ${PHPH}
    LimitRequestBody 12582912
</VirtualHost>
APACHE
  a2ensite verifyxpeed >/dev/null
  apachectl configtest && systemctl reload apache2
else
  WEB=nginx
  command -v nginx >/dev/null || apt-get install -y nginx
  [ -n "$SOCK" ] || apt-get install -y "php${PHPV}-fpm"
  SOCK=$(ls /run/php/php${PHPV}-fpm.sock 2>/dev/null || ls /run/php/php*-fpm.sock | head -1)
  say "Nginx site for ${DOMAIN}"
  cat > /etc/nginx/sites-available/verifyxpeed <<NGINX
server {
    listen 80;
    server_name ${DOMAIN};
    root ${APP_DIR}/public;
    index index.php;
    client_max_body_size 12M;
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ { include snippets/fastcgi-php.conf; fastcgi_pass unix:${SOCK}; fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name; }
    location ~ /\.(?!well-known) { deny all; }
}
NGINX
  ln -sf /etc/nginx/sites-available/verifyxpeed /etc/nginx/sites-enabled/verifyxpeed
  nginx -t && systemctl reload nginx
fi

say "Background worker (bulk CSV jobs)"
cat > /etc/supervisor/conf.d/verifyxpeed.conf <<SUP
[program:verifyxpeed-worker]
command=php ${APP_DIR}/artisan queue:work --sleep=3 --tries=1 --timeout=3600
user=www-data
autostart=true
autorestart=true
stdout_logfile=${APP_DIR}/storage/logs/worker.log
SUP
supervisorctl reread && supervisorctl update && supervisorctl restart verifyxpeed-worker || true

say "Free SSL (Let's Encrypt)"
command -v certbot >/dev/null || apt-get install -y certbot
apt-get install -y "python3-certbot-${WEB}" >/dev/null 2>&1 || true
if certbot --${WEB} -d "$DOMAIN" --non-interactive --agree-tos -m "$ADMIN_EMAIL" --redirect; then SCHEME=https; else SCHEME=http; echo "SSL skipped for now (site works on http). Retry later with: certbot --${WEB} -d ${DOMAIN}"; fi
sed -i "s#^APP_URL=.*#APP_URL=${SCHEME}://${DOMAIN}#" .env && php artisan config:cache >/dev/null
chown -R www-data:www-data storage bootstrap/cache database

say "Done! Open ${SCHEME}://${DOMAIN}"
echo "Admin login: ${ADMIN_EMAIL}  /  password: ${ADMIN_PASS}   (change it in Settings)"
echo "Next: log in as admin, open Cashfree & logs, paste your Cashfree keys, Save, then Test connection."
echo "Server IP to whitelist in Cashfree Secure ID: $(curl -s https://api.ipify.org || hostname -I | awk '{print $1}')"
