#!/usr/bin/env bash
# ตั้งระบบคลินิกสำหรับพัฒนาใน WSL (Ubuntu 24.04 + nginx + PHP 8.4 + MySQL 8.4) — 2569-10-21
# ใช้ฐานทดสอบที่ปิดตัวตนคนไข้แล้ว (clinic_test) · ทำตาม docs/deploy-linux.md ให้ใกล้ server จริงที่สุด
set -euo pipefail
export DEBIAN_FRONTEND=noninteractive COMPOSER_ALLOW_SUPERUSER=1
SRC=/mnt/d/xampp/clinic-test
APP=/var/www/clinic-dev
SHARE=/mnt/d/WSL/share
PORT=8444
REVERB_PORT=8091

apt-get install -y -qq rsync openssl >/dev/null

echo "== code"
mkdir -p "$APP"
rsync -a --delete \
  --exclude .git --exclude vendor --exclude node_modules --exclude public/vendor --exclude public/queue/vendor \
  --exclude 'storage/logs/*' --exclude 'storage/framework/sessions/*' --exclude 'storage/framework/views/*' \
  --exclude 'storage/framework/cache/data/*' --exclude 'bootstrap/cache/*.php' --exclude .vscode \
  "$SRC/" "$APP/"
mkdir -p "$APP"/storage/{logs,framework/{sessions,views,cache/data},app/private}

echo "== database"
DBPASS_FILE=/root/.clinic_dev_dbpass
[ -f "$DBPASS_FILE" ] || openssl rand -hex 16 > "$DBPASS_FILE"
DBPASS=$(cat "$DBPASS_FILE")
mysql <<SQL
CREATE DATABASE IF NOT EXISTS clinic_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS queue_system_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'clinic_app'@'127.0.0.1' IDENTIFIED BY '$DBPASS';
ALTER USER 'clinic_app'@'127.0.0.1' IDENTIFIED BY '$DBPASS';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES ON clinic_test.* TO 'clinic_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON queue_system_test.* TO 'clinic_app'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
if [ "$(mysql -N -e 'select count(*) from information_schema.tables where table_schema="clinic_test"')" = "0" ]; then
  mysql clinic_test < "$SHARE/clinic_test.sql"
  mysql queue_system_test < "$SHARE/queue_system_test.sql"
fi
echo "tables: $(mysql -N -e 'select count(*) from information_schema.tables where table_schema="clinic_test"') · patients: $(mysql -N -e 'select count(*) from clinic_test.patients')"

echo "== .env"
cd "$APP"
cp "$SRC/.env" .env
setenv() { if grep -q "^$1=" .env; then sed -i "s#^$1=.*#$1=$2#" .env; else echo "$1=$2" >> .env; fi; }
sed -i 's/\r$//' .env
setenv APP_URL "https://localhost:$PORT"
setenv DB_HOST 127.0.0.1
setenv DB_PORT 3306
setenv DB_DATABASE clinic_test
setenv DB_QUEUE_DATABASE queue_system_test
setenv DB_USERNAME clinic_app
setenv DB_PASSWORD "$DBPASS"
setenv SESSION_COOKIE clinic_dev_session
setenv REVERB_PORT $REVERB_PORT
setenv REVERB_SERVER_PORT $REVERB_PORT
setenv R506_DATA_DIR "$APP/resources/r506"
if [ -f public/queue/config/db_connect.php ]; then
  sed -i 's/\r$//; s/\$servername *= *"[^"]*"/$servername = "127.0.0.1"/; s/\$port *= *[0-9]*/$port = 3306/' public/queue/config/db_connect.php
  sed -i "s/\\\$username *= *\"[^\"]*\"/\$username = \"clinic_app\"/; s/\\\$password *= *\"[^\"]*\"/\$password = \"$DBPASS\"/; s/\\\$database *= *\"[^\"]*\"/\$database = \"queue_system_test\"/" public/queue/config/db_connect.php
fi

echo "== composer"
composer install --no-interaction --optimize-autoloader -q
[ -f public/queue/composer.json ] && (cd public/queue && composer install --no-interaction -q --no-dev) || true
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache
sudo -u www-data php artisan config:cache -q
sudo -u www-data php artisan view:clear -q
sudo -u www-data php artisan migrate --force

echo "== php-fpm"
INI=/etc/php/8.4/fpm/conf.d/99-clinic.ini
cat > "$INI" <<INI
expose_php = Off
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 512M
max_execution_time = 120
date.timezone = Asia/Bangkok
INI
cp "$INI" /etc/php/8.4/cli/conf.d/99-clinic.ini

echo "== nginx"
mkdir -p /etc/ssl/clinic-dev
[ -f /etc/ssl/clinic-dev/cert.pem ] || openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
  -keyout /etc/ssl/clinic-dev/key.pem -out /etc/ssl/clinic-dev/cert.pem -subj "/CN=localhost" >/dev/null 2>&1
cat > /etc/nginx/sites-available/clinic-dev <<NGINX
server {
    listen $PORT ssl;
    server_name localhost;
    root $APP/public;
    index index.php;
    ssl_certificate     /etc/ssl/clinic-dev/cert.pem;
    ssl_certificate_key /etc/ssl/clinic-dev/key.pem;
    server_tokens off;
    client_max_body_size 25m;

    location ~ (^|/)vendor/.*\.(php|phar|json|lock|md|txt|xml|dist|sh|yml|yaml)\$ { return 403; }
    location ~ (^|/)(composer\.(json|lock)|package(-lock)?\.json|mix-manifest\.json|\.env.*|\.git.*)\$ { return 403; }
    location ~ /\.(?!well-known) { deny all; }

    location /app/ {
        proxy_pass http://127.0.0.1:$REVERB_PORT;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host \$host;
        proxy_read_timeout 120s;
    }
    location / { try_files \$uri \$uri/ /index.php?\$query_string; }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_read_timeout 120s;
    }
}
NGINX
ln -sf /etc/nginx/sites-available/clinic-dev /etc/nginx/sites-enabled/clinic-dev
rm -f /etc/nginx/sites-enabled/default
nginx -t -q
systemctl restart php8.4-fpm nginx

echo "== reverb + scheduler"
cat > /etc/systemd/system/clinic-dev-reverb.service <<UNIT
[Unit]
Description=Clinic dev Reverb
After=network.target mysql.service
[Service]
User=www-data
WorkingDirectory=$APP
ExecStart=/usr/bin/php artisan reverb:start
Restart=always
RestartSec=5
[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now clinic-dev-reverb >/dev/null 2>&1
echo "* * * * * cd $APP && php artisan schedule:run >> /dev/null 2>&1" | crontab -u www-data -

echo "== check"
sleep 2
curl -sk -o /dev/null -w "login %{http_code}\n" https://localhost:$PORT/login
curl -sk https://localhost:$PORT/up; echo
sudo -u www-data php artisan --version
sudo -u www-data php artisan clinic:smoke-test | tail -3
