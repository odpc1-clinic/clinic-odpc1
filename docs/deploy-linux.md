# ติดตั้งระบบคลินิก (odpc1-clinic) บน Linux + nginx

สำหรับ: กลุ่ม IT สคร.1 · จัดทำ 2569-10-20 · ปรับ 2569-10-21 · ระบบ: Laravel 13 (branch `laravel13`, PHP ≥ 8.3) + MySQL · ผู้ใช้ประมาณ 20 คน ในเครือข่ายภายใน

> ทดสอบคู่มือนี้แล้วบน Ubuntu 24.04 + nginx + PHP 8.4 + MySQL 8.4 (เครื่องพัฒนา WSL) — ผ่านทุกขั้น
> server ที่ติดตั้ง **CloudPanel**: สร้างเว็บแบบ "PHP / Laravel" ใน CloudPanel แทนข้อ 6 (nginx) และใช้ฐานข้อมูล/ผู้ใช้จาก CloudPanel แทนข้อ 3
> — โฟลเดอร์จะเป็น `/home/<site-user>/htdocs/<โดเมน>` แทน `/var/www/odpc1-clinic` และผู้ใช้ของเว็บเป็น `<site-user>` แทน `www-data`
> ส่วนที่ CloudPanel ไม่ได้ทำให้ (ต้องเพิ่มเอง): location `/app/` ของ Reverb, กฎปิด vendor/manifest, cron, service ของ Reverb
> **ห้ามเปิด Varnish Cache** ของเว็บนี้ (แท็บ Varnish Cache ต้องปิด) — Varnish จำหน้าแล้วส่งซ้ำให้คนอื่น ข้อมูลคนไข้จะไปโผล่ผิดคน
> template ของ CloudPanel ส่งต่อ 443 → nginx พอร์ต 8080 → PHP จึงต้องใส่ `TRUSTED_PROXIES=127.0.0.1` ใน `.env`
> (ไม่งั้นทุกคนดูเหมือนมาจาก 127.0.0.1 — ตัวกันเดารหัสผ่านจะล็อกทุกคนพร้อมกัน) · location `/app/` ใส่ใน server block 443 ก่อน `location /`

> ระบบนี้เก็บ**เวชระเบียนผู้ป่วย (รวมผล HIV)** — เครื่องต้องอยู่ในเครือข่ายภายในเท่านั้น
> เปิดออกอินเทอร์เน็ตได้เฉพาะ `/line/webhook` (ดูข้อ 8)

---

## 1. ซอฟต์แวร์ที่ต้องมี

| ซอฟต์แวร์ | รุ่น |
|---|---|
| PHP-FPM | 8.4 (8.2 ขึ้นไปใช้ได้) |
| PHP extension | `pdo_mysql mysqli mbstring openssl gd zip intl fileinfo bcmath curl xml dom tokenizer ctype sockets pcntl` |
| Composer | 2.x |
| MySQL | 8.4 LTS |
| nginx | รุ่นปัจจุบัน |
| systemd, cron | มีในเครื่องอยู่แล้ว |

ตรวจ extension: `php -m`

## 2. วางโค้ด

```bash
sudo mkdir -p /var/www/odpc1-clinic
sudo unzip odpc1-clinic-code.zip -d /var/www/odpc1-clinic
cd /var/www/odpc1-clinic
composer install --no-dev --optimize-autoloader
(cd public/queue && composer install --no-dev)          # ระบบเรียกคิวใช้ไลบรารีแยก
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

## 3. ฐานข้อมูล

```sql
CREATE DATABASE clinic        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE queue_system  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ผู้ใช้เฉพาะของระบบ ไม่ใช้ root
CREATE USER 'clinic_app'@'127.0.0.1' IDENTIFIED BY '<รหัสผ่านยาว>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES
      ON clinic.* TO 'clinic_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON queue_system.* TO 'clinic_app'@'127.0.0.1';
```

`/etc/mysql/mysql.conf.d/mysqld.cnf` → `bind-address = 127.0.0.1` (ระบบต่อจากเครื่องตัวเองเท่านั้น)

ไฟล์ข้อมูล (`clinic.sql.gz`, `queue_system.sql.gz`) ผู้ดูแลระบบคลินิกส่งให้แยกต่างหากในวันย้าย:
```bash
gunzip -c clinic.sql.gz       | mysql -u root -p clinic
gunzip -c queue_system.sql.gz | mysql -u root -p queue_system
```

## 4. ไฟล์ตั้งค่า `.env`

คัดลอก `.env` จากผู้ดูแลระบบคลินิก (มีค่าลับ) แล้วแก้เฉพาะบรรทัดเหล่านี้:

```ini
APP_URL=https://odpc1-clinic.<โดเมนภายใน>
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=clinic_app
DB_PASSWORD=<รหัสข้อ 3>
REVERB_PORT=8081
REVERB_SERVER_PORT=8081
```

**ห้ามสร้าง `APP_KEY` ใหม่** — ไฟล์สแกน/รูปของคนไข้ถูกเข้ารหัสด้วยคีย์เดิม ถ้าเปลี่ยนจะเปิดไฟล์เดิมไม่ได้ทั้งหมด

ระบบเรียกคิว: `public/queue/config/db_connect.php` (ผู้ดูแลระบบคลินิกส่งให้) — ใช้ `127.0.0.1` ไม่ใช้ `localhost`
(บน Linux `localhost` ของ mysqli จะต่อผ่าน socket แล้วไม่สนพอร์ต)

แล้วรัน:
```bash
php artisan migrate --force
php artisan config:cache
php artisan view:cache
php artisan storage:link
```
(ไม่ต้อง `route:cache`)

## 5. ไฟล์ของคนไข้

คัดลอกโฟลเดอร์ `storage/app/private/` จากเครื่องเดิมมาวางที่เดียวกัน (ไฟล์เข้ารหัสอยู่แล้ว) แล้ว `chown -R www-data:www-data storage`

## 6. nginx

`/etc/nginx/sites-available/odpc1-clinic`:

```nginx
server {
    listen 80;
    server_name odpc1-clinic.<โดเมนภายใน>;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name odpc1-clinic.<โดเมนภายใน>;
    root /var/www/odpc1-clinic/public;
    index index.php;

    ssl_certificate     /etc/ssl/odpc1-clinic/fullchain.pem;
    ssl_certificate_key /etc/ssl/odpc1-clinic/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    server_tokens off;
    client_max_body_size 25m;          # แนบไฟล์ผล Lab/เอกซเรย์ ไม่เกิน 10–20 MB
    add_header Strict-Transport-Security "max-age=31536000" always;   # ใส่เมื่อใบรับรองเป็นของจริงแล้วเท่านั้น

    # ไฟล์ภายในของไลบรารี / manifest — ห้ามเปิดผ่านเว็บ
    location ~ (^|/)vendor/.*\.(php|phar|json|lock|md|txt|xml|dist|sh|yml|yaml)$ { return 403; }
    location ~ (^|/)(composer\.(json|lock)|package(-lock)?\.json|mix-manifest\.json|\.env.*|\.git.*)$ { return 403; }
    location ~ /\.(?!well-known) { deny all; }

    # จอรีเฟรชทันที (Laravel Reverb) — WebSocket
    location /app/ {
        proxy_pass http://127.0.0.1:8081;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 120s;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_read_timeout 120s;      # รายงาน/ส่งออก Excel บางหน้าใช้เวลา
    }
}
```

`/etc/php/8.4/fpm/php.ini`:
```ini
expose_php = Off
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 512M
max_execution_time = 120
date.timezone = Asia/Bangkok
```

## 7. งานเบื้องหลัง

**ตัวตั้งเวลา** (แจ้งนัดทาง LINE, เฝ้าระบบล่ม, ตรวจช่องโหว่) — `sudo crontab -u www-data -e`:
```
* * * * * cd /var/www/odpc1-clinic && php artisan schedule:run >> /dev/null 2>&1
```

**Reverb** — `/etc/systemd/system/clinic-reverb.service`:
```ini
[Unit]
Description=Clinic Reverb (real-time screens)
After=network.target mysql.service

[Service]
User=www-data
WorkingDirectory=/var/www/odpc1-clinic
ExecStart=/usr/bin/php artisan reverb:start
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now clinic-reverb
```

(ระบบเรียกคิวมี `public/queue/server.php` เป็น WebSocket พอร์ต 8080 แต่จอคิวปัจจุบันไม่ได้ใช้ — ไม่ต้องตั้ง service)

## 8. LINE webhook (ต้องเข้าจากอินเทอร์เน็ตได้)

LINE ต้องเรียก `https://<ชื่อสาธารณะ>/line/webhook` ได้ — ใช้ตอนคนไข้พิมพ์รหัสผูกบัญชี และผู้ดูแลผูกรับแจ้งเตือนระบบล่ม
(ส่งข้อความ**ออก**ไม่ต้องใช้ทางนี้ — ข้อ 9)

**ทาง ก (แนะนำ)** — IT เปิดทางเข้าจากอินเทอร์เน็ตแบบถาวร **เฉพาะ path นี้** (reverse proxy ที่ส่งต่อเฉพาะ `/line/webhook`)
path อื่นทั้งหมดต้องไม่ออกอินเทอร์เน็ต · แล้วผู้ดูแลระบบคลินิกตั้ง URL ใน LINE ครั้งเดียว:
```bash
php artisan line:set-webhook https://<ชื่อสาธารณะ>
```

**ทาง ข (ถ้าเปิดทาง ก ไม่ได้)** — Cloudflare tunnel บนเครื่องนี้ (แบบที่เครื่องเดิมใช้)
เครื่องเดิมใช้ quick tunnel ซึ่งได้ URL ใหม่ทุกครั้งที่รีสตาร์ต แล้วมีสคริปต์ตั้ง URL ใน LINE ให้เอง
บน server ใหม่ควรใช้ **named tunnel** (ต้องมีบัญชี Cloudflare + โดเมน) URL จะคงที่ · ติดตั้ง `cloudflared` เป็น systemd service
และใน nginx ต้องปิดทุก path ที่มาทาง tunnel ยกเว้น `/line/webhook`:
```nginx
# ใส่ใน server block — คำขอจาก Cloudflare มี header CF-Ray
location / {
    if ($http_cf_ray) { return 403; }
    try_files $uri $uri/ /index.php?$query_string;
}
location = /line/webhook { try_files $uri /index.php?$query_string; }
location /app/ { if ($http_cf_ray) { return 403; } ... }   # Reverb ด้วย
```

## 9. Firewall

**ขาเข้า:** 443 (และ 80 เพื่อ redirect) จากวงของคลินิก `<CLINIC_LAN>, <CLINIC_LAN>, <CLINIC_LAN>, <CLINIC_LAN>`
· SSH (22) และหน้า CloudPanel (8443) เฉพาะเครื่องผู้ดูแล `<ADMIN_PC_IP>` · พอร์ตอื่น (3306, 8081) ไม่ต้องเปิด

**ขาออก (ระบบต้องออกอินเทอร์เน็ตไปที่นี่ได้):**

| ปลายทาง | ใช้ทำอะไร |
|---|---|
| `api.line.me` | ส่ง LINE แจ้งนัด/ยืนยันนัด/แจ้งระบบล่ม · ตอบข้อความผูกบัญชี |
| `script.google.com`, `script.googleusercontent.com` | ดึงคลิป/รูป TB DOT ที่คนไข้ส่ง (Google Apps Script relay) |
| `github.com`, `codeload.github.com`, `packagist.org`, `repo.packagist.org` | ติดตั้ง/อัปเดตไลบรารี (`composer install`) |
| `ndi.fda.moph.go.th` | นำเข้าราคายา อย. (ใช้นาน ๆ ครั้ง) |

## 9.1 ของที่ต้องย้าย / ตั้งใหม่ จากเครื่องเดิม (Windows)

| รายการ | เครื่องเดิม | server ใหม่ |
|---|---|---|
| ค่าลับ LINE (`LINE_CHANNEL_ACCESS_TOKEN`, `LINE_CHANNEL_SECRET`) | `.env` | คัดลอกจาก `.env` เดิม |
| TB DOT relay (`TB_DOT_RELAY_URL`, `TB_DOT_RELAY_SECRET`) | `.env` | คัดลอกจาก `.env` เดิม |
| `APP_KEY` | `.env` | **คัดลอกจากเดิม ห้ามสร้างใหม่** (ข้อ 4) |
| ผู้รับแจ้งเตือนระบบล่ม | `storage/app/health-alert.json` | คัดลอกไฟล์มา (หรือผูก LINE ใหม่ในหน้า Dashboard งานพัฒนา) |
| ไฟล์คนไข้ (สแกน/รูป/คลิป DOT) | `storage/app/private/` | คัดลอกทั้งโฟลเดอร์ (ข้อ 5) |
| ตัวตั้งเวลา | Windows Task `ClinicLaravelScheduler` | cron (ข้อ 7) |
| Reverb | Windows Task `ClinicReverb` | systemd (ข้อ 7) |
| LINE webhook | Cloudflare quick tunnel (`C:\cloudflared`) | ข้อ 8 แล้ว**ปิด tunnel บนเครื่องเดิม** (ไม่งั้น LINE ยิงเข้าเครื่องเดิม) |
| ระบบเรียกคิว | Laragon พอร์ต 80 (`D:\laragon\www\queue`) | มากับโค้ดแล้วที่ `https://<server>/queue` · ตั้ง `public/queue/config/db_connect.php` (ใช้ `127.0.0.1`) · **เปลี่ยนลิงก์บนจอคิวทุกจอ** |
| ตาราง R506 (จังหวัด/อำเภอ/ตำบล) | `C:\r506\databackup` | มากับโค้ดแล้ว (`resources/r506`) ไม่ต้องทำอะไร |
| เฝ้าระบบข้ามเครื่อง | — | เครื่องเดิมรัน `clinic:health-watch` ชี้มา server ใหม่ (`HEALTH_WATCH_URL=https://<server>/up`) จะได้แจ้งเมื่อ server ดับทั้งเครื่อง |

**ไม่ต้องย้าย:** workerman (พอร์ต 8080 ไม่มีจอไหนใช้) · phpMyAdmin (ใช้ของ CloudPanel)

**อยู่บนเครื่องเจ้าหน้าที่ ไม่เกี่ยวกับ server:** QZ Tray (สั่งพิมพ์) · FAST ID (อ่านบัตรประชาชน) · INFINITT PACS · EFormAgent · PassportStamp
— ก่อนวันย้ายให้ลองพิมพ์ใบเสร็จ/ใบนัด/สติ๊กเกอร์จากที่อยู่ใหม่ในแต่ละห้อง (QZ Tray อาจถามอนุญาตใหม่ครั้งแรก)

## 10. ตรวจหลังติดตั้ง

```bash
php artisan clinic:smoke-test        # เปิดทุกหน้าหลัก ต้องผ่านทั้งหมด
php artisan clinic:security-audit    # ช่องโหว่ไลบรารี ต้องเป็น 0
curl -k https://localhost/up         # สถานะระบบ ต้อง "ok"
```
เปิดเว็บแล้วเข้าระบบได้ · ส่งคนไข้ข้ามห้องแล้วจอปลายทางขยับเอง (Reverb ทำงาน)

---
