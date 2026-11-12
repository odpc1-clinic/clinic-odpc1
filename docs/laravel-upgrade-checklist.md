# Checklist อัปเกรด Laravel 8 → 12 (ระบบคลินิก STI)

> จัดทำ 21 ก.ค. 2569 · ทุกตัวเลขในเอกสารนี้วัดจากโค้ดจริงในเครื่อง ไม่ใช่ค่าประมาณ
> ตรวจซ้ำได้ด้วย `composer why-not laravel/framework <เวอร์ชัน>`

---

## สถานะปัจจุบัน

| | |
|---|---|
| Laravel | **8.83.29** (สาย 8 หมด security support ม.ค. 2566) |
| PHP (CLI / artisan / scheduler) | **8.2.4** — `D:\xampp\php\php.exe` |
| PHP (Apache / เว็บที่คนไข้ใช้จริง) | **8.1.25** — `D:/xampp/php/php8.1/php8apache2_4.dll` |
| ขนาดงาน | 56 controllers · 36 models · **214 blade** · 118 routes · 20 migrations |
| **Test อัตโนมัติ** | **2 ไฟล์ (ของ Laravel ที่แถมมา = แทบไม่มี)** |
| ช่องโหว่ค้าง | 30 advisories (ปิดเพิ่มไม่ได้ถ้าไม่ขึ้น major) |

### ⚠️ ปัญหาที่ต้องแก้ก่อนเริ่มอัปเกรด

**1. PHP ของ Apache กับ CLI คนละเวอร์ชัน**
เว็บรันบน 8.1.25 แต่ artisan/scheduler/composer รันบน 8.2.4 — composer แก้ dependency โดยคิดว่าเป็น 8.2
ตอนนี้ยังไม่พังเพราะยังไม่มี package ไหนบังคับ 8.2 แต่ **วันที่มี จะพังเฉพาะฝั่งเว็บ ส่วน CLI ยังเขียวหมด** = หาสาเหตุยากมาก

- Laravel 10 ต้องการ PHP ≥ 8.1 → ผ่าน
- **Laravel 11 / 12 ต้องการ PHP ≥ 8.2 → Apache ต้องเปลี่ยนไปใช้ 8.2 ก่อน** (แก้ `LoadModule php_module` ใน `httpd-vhosts.conf` + `PHPINIDir` ใน `httpd-xampp.conf`)

**2. ไม่มี test และไม่มี staging** → ต้องทดสอบมือทุกขั้น ตาม §7

---

## 0. เตรียมตัว (ห้ามข้าม)

- [ ] **ทำสำเนาระบบทั้งชุดไปเครื่อง/โฟลเดอร์อื่น แล้วอัปเกรดบนสำเนาเท่านั้น** ห้ามแก้บนเครื่องที่คนไข้ใช้
- [ ] Dump ฐานข้อมูล `clinic` + `queue_system` เก็บไว้
- [ ] `git checkout -b upgrade/laravel-9` — commit ทีละขั้น ย้อนได้
- [ ] สำรอง `composer.json` + `composer.lock`
- [ ] จดเวอร์ชันที่ใช้อยู่: `composer show > before.txt`
- [ ] เลือกวันทำ: **อังคาร/พฤหัส** (STI ปิด เปิดแต่ TB) — STI เปิด จ/พ/ศ

---

## 1. ล้างของไม่ได้ใช้ก่อน — ได้กำไรฟรี

ตรวจแล้วว่า **4 ตัวนี้ไม่ถูกเรียกใช้เลยในโค้ด** แต่บล็อกการอัปเกรดอยู่ ถอดทิ้งได้เลยโดยไม่ต้องแก้โค้ด:

| Package | บล็อก | ใช้จริง |
|---|---|---|
| `appzcoder/crud-generator` | L10 | **0 จุด** |
| `cboden/ratchet` | — | **0 จุด** |
| `google/cloud-text-to-speech` | — | **0 จุด** (เสียงเรียกคิวใช้ MP3 อัดไว้ ไม่ได้ใช้ TTS) |
| `spatie/laravel-csp` | L11 | **0 จุด** |
| `uxweb/sweet-alert` | **L9** | 1 จุด — แค่บรรทัด ServiceProvider ใน `config/app.php:182` (ตัวจริงที่ใช้คือ `realrashid/sweet-alert` 56 จุด) |

- [ ] `composer remove appzcoder/crud-generator cboden/ratchet google/cloud-text-to-speech spatie/laravel-csp uxweb/sweet-alert`
- [ ] ลบบรรทัด `UxWeb\SweetAlert\SweetAlertServiceProvider::class` ใน `config/app.php`
- [ ] ทดสอบตาม §7 → commit

> ทำแค่ข้อนี้ก็ปลดล็อกตัวบล็อก L9 ไป 1 ตัว และ L10/L11 ไปอย่างละ 1 ตัวแล้ว

---

## 2. L8 → L9

**ตัวบล็อก (จาก `composer why-not`)**

| Package | ต้องทำ |
|---|---|
| `facade/ignition` + `facade/flare-client-php` | เปลี่ยนเป็น **`spatie/laravel-ignition`** (เปลี่ยนเจ้าของแพ็กเกจ) |
| `uxweb/sweet-alert` | ถอดแล้วใน §1 ✅ |

**สิ่งที่ต้องแก้ในโค้ด**

- [ ] `composer.json`: `"laravel/framework": "^9.0"`, `"php": "^8.0.2"`
- [ ] เอา `facade/ignition` ออก ใส่ `spatie/laravel-ignition ^1.0`
- [ ] `app/Exceptions/Handler.php` — ลบ `use Facade\Ignition\...` ถ้ามี
- [ ] Flysystem 1 → 3 (Laravel จัดการเอง แต่ถ้ามีโค้ดเรียก `Storage::` แบบพิเศษให้เช็ค)
- [ ] SwiftMailer → Symfony Mailer — **ระบบนี้ไม่มีโค้ดเรียก `Swift_` เลย (0 จุด) ผ่านฉลุย**
- [ ] `fzaninotto/faker` → `fakerphp/faker` (dev เท่านั้น)

**ระดับความเสี่ยง: 🟢 ต่ำ** — ระบบนี้ไม่ใช้ mail/queue/broadcast จริงจัง

---

## 3. L9 → L10

**ตัวบล็อก**

| Package | ต้องทำ |
|---|---|
| `fideloper/proxy` | **ถอดทิ้ง** Laravel มี `TrustProxies` ในตัวแล้ว → แก้ `app/Http/Middleware/TrustProxies.php` ให้ `extends Illuminate\Http\Middleware\TrustProxies` |
| `fruitcake/laravel-cors` | **ถอดทิ้ง** Laravel มี `HandleCors` ในตัว → แก้ `app/Http/Kernel.php` |
| `laravel/ui` 3 → **4** | มี `Auth::routes()` ที่ `routes/web.php:71` — ต้องเช็คว่า view auth ยังใช้ได้ |
| `yajra/laravel-datatables-oracle` 9 → **10** | ใช้ 9 จุด |
| `spatie/laravel-package-tools` | อัปตาม (transitive) |

**สิ่งที่ต้องแก้ในโค้ด**

- [ ] PHP ≥ 8.1 (Apache ปัจจุบัน 8.1.25 → ผ่านพอดี)
- [ ] `laravel/framework": "^10.0"`
- [ ] Monolog 2 → 3 (กระทบเฉพาะถ้ามี custom log formatter — ระบบนี้ไม่มี)
- [ ] ตรวจ type hint ที่เข้มขึ้นใน `Illuminate\Contracts\*` ที่ implement เอง

**ระดับความเสี่ยง: 🟡 กลาง** — DataTables 9→10 เปลี่ยน API พอสมควร

---

## 4. L10 → L11 — **ขั้นที่ยากที่สุด**

**⛔ ต้องเปลี่ยน PHP ของ Apache เป็น 8.2 ก่อนขั้นนี้**

**ตัวบล็อก 6 ตัว**

| Package | ใช้ | ต้องทำ |
|---|---|---|
| **`laravelcollective/html`** | **30 จุด / 8 ไฟล์** | **แพ็กเกจถูกทิ้งถาวร หยุดที่ L10** → ต้องเขียน HTML form แทน หรือย้ายไป `spatie/laravel-html` |
| `laracasts/flash` | **34 จุด** | อัปเป็น 3.2.6+ (รองรับ L11) |
| `spatie/laravel-permission` 5 → **6** | ทั้งระบบสิทธิ์ | **breaking**: วิธีลงทะเบียน middleware เปลี่ยน, `role`/`permission` alias ต้องประกาศใหม่ |
| `milon/barcode` v10 → v11+ | **1 จุด** (`DNS2D::getBarcodeSVG` ทำ QR ใบคิว) | อัปเวอร์ชัน |
| `laravel/tinker` 2.8 → 2.9+ | — | อัปเวอร์ชัน |
| `laravel/ui` | — | อัปตาม |
| `spatie/laravel-csp` | — | ถอดแล้วใน §1 ✅ |

### 🔴 จุดที่จะพังแน่ๆ: `Form::` 30 จุด

**ข่าวดี: ทั้ง 8 ไฟล์เป็นหน้าจัดการผู้ใช้/สิทธิ์ของแอดมิน ไม่ใช่หน้างานคลินิก** — ถ้าพังไม่กระทบการตรวจคนไข้

```
resources/views/backend/role/index.blade.php
resources/views/backend/shared/_actions.blade.php
resources/views/backend/shared/_permissions.blade.php
resources/views/backend/user/edit.blade.php
resources/views/backend/user/new.blade.php
resources/views/backend/user/_form.blade.php
resources/views/shared/_actions.blade.php
resources/views/shared/_permissions.blade.php
```

เมธอดที่ใช้: `Form::label` 6 · `Form::close` 6 · `Form::text` 4 · `Form::submit` 4 · `Form::open` 4 · `Form::model` 2 · `Form::checkbox` 2 · `Form::select` 1

- [ ] แปลงเป็น HTML ธรรมดา + `@csrf` / `@method('PUT')` (งานประมาณครึ่งวัน)
- [ ] `Form::model` ต้องเติม `value="{{ old('x', $model->x) }}"` เอง

**ระดับความเสี่ยง: 🔴 สูง** — ทำแยกวันจากขั้นอื่น

---

## 5. L11 → L12

| Package | ต้องทำ |
|---|---|
| `realrashid/sweet-alert` v7.2 | ตอนตรวจ (ก.ค. 2569) เวอร์ชันล่าสุดรองรับถึง L11 — **ต้องเช็คใหม่ตอนถึงขั้นนี้** ถ้ายังไม่รองรับต้องหาตัวแทน (ใช้อยู่ 56 จุด) |
| ตัวอื่น | ส่วนใหญ่ตามน้ำ |

**ระดับความเสี่ยง: 🟢 ต่ำ** — L12 เป็น maintenance release เป็นหลัก

---

## 6. จุดที่จะพังเฉพาะระบบนี้ (ไม่มีในคู่มืออัปเกรดทั่วไป)

### 🔴 6.1 วันที่ พ.ศ. + Carbon 2 → 3

ระบบเก็บ `visits.date` / `visits.appointment` เป็น **พ.ศ. `Y-m-d`** (เช่น `2569-07-20`) ซึ่งไม่ใช่วันที่จริงในสายตา PHP

- `Utillity::` (AD2BE / BE2AD / th2dbDate / db2thDate) **43 จุด**
- `Carbon::parse` **45 จุด** · `->format()` **85 จุด** · `createFromFormat` 5 จุด

**Carbon 3 เปลี่ยน `diffIn*` ให้คืนค่า float และมีเครื่องหมายบวก/ลบ** (Carbon 2 คืน int และเป็นค่าสัมบูรณ์)
ในระบบนี้: `diffInDays` 3 จุด · `diffInMinutes` 2 จุด · `diffForHumans` 1 จุด

- [ ] ไล่ดู 6 จุดนี้ทีละจุด เทียบผลก่อน/หลัง
- [ ] **ทดสอบเคสข้ามปี พ.ศ.** และเคสวันที่ 29 ก.พ.
- [ ] ทดสอบ "แจ้งเตือนนัดวันจันทร์" (`SendAppointmentReminders`) เพราะคำนวณวันล่วงหน้า

### 🟡 6.2 `$_GET` 63 จุด

ใช้ superglobal ตรงๆ ในหน้า visit/edit (เช่น `$_GET['page']` สลับแท็บ) — ยังทำงานทุกเวอร์ชัน แต่ถ้าคีย์หายจะ **warning ท่วม log** (เห็นแล้วตอนทดสอบ) ถือโอกาสเปลี่ยนเป็น `request('page', 1)` ตอนอัป

### 🟡 6.3 การเชื่อมฐานข้อมูล 2 ตัว

`queue_system` (MySQL พอร์ต 3307) ใช้ร่วมกับโปรแกรมเรียกคิวของ Laragon — **ห้ามให้ migration ของ Laravel ไปแตะตารางนี้**

- [ ] ตรวจว่า `php artisan migrate` ไม่วิ่งไปที่ connection `queue_system`

### 🟡 6.4 spatie/laravel-permission 5 → 6

ทั้งระบบใช้ role/permission คุมสิทธิ์ (Admin/Doctor/Nurse/Registrar/Lab/Pharmacy/Guest) และมี middleware alias `role` ที่เขียนเอง

- [ ] ทดสอบ **ทุก role** ว่าเข้าหน้าที่ควรเข้าได้/ไม่ได้ตรงเดิม
- [ ] ย้ำ: `/line/health` ต้อง 403 สำหรับคนที่ไม่ใช่ Admin

### 🟡 6.5 Excel export

`maatwebsite/excel` + `phpoffice/phpspreadsheet` — export ภาษาไทย

- [ ] ทดสอบ export แล้ว **เปิดไฟล์จริงใน Excel** ดูว่าภาษาไทยไม่เพี้ยน

---

## 7. แผนทดสอบหลังทุกขั้น (ทำซ้ำทุกครั้ง)

ระบบไม่มี test อัตโนมัติ → ต้องไล่มือตามนี้

**สโมกเทสต์เร็ว (ทุกขั้น)**
- [ ] `php artisan --version` ผ่าน · `php artisan config:cache` ผ่าน
- [ ] เปิดได้: `/login` `/home` `/patient` `/visit` `/lab` `/pharmacy` `/medicine` `/line/health`

**งานคลินิกจริง (ขั้น L10→L11 ขึ้นไปต้องทำครบ)**
- [ ] ลงทะเบียนคนไข้ใหม่ + อ่านบัตรประชาชน
- [ ] เปิด visit ใหม่ → รับคนไข้ → ตรวจ → บันทึก
- [ ] **autosave** ช่องอาการ/ตรวจร่างกาย/คำแนะนำ
- [ ] popup "ข้อความเดิม" + "ยาเดิม" + "วินิจฉัยเดิม" + ผล Lab ย้อนหลัง
- [ ] สั่งยา → คิดเงิน → พิมพ์ใบเสร็จ
- [ ] ห้อง Lab ลงผล → **อนุมัติผล** → ฝั่งหมอเห็นผล
- [ ] พิมพ์: ใบนัด · ใบรับรองแพทย์ · บัตรคิว · ผล Lab
- [ ] เรียกคิว → **ขึ้นจอ terminal + มีเสียง**
- [ ] แจ้งเตือน LINE: `php artisan reminders:send-appointments`
- [ ] Export Excel แล้วเปิดไฟล์จริง
- [ ] ทดสอบครบทุก role

---

## 8. แผนถอย

| ระดับ | วิธี |
|---|---|
| แก้ผิดเล็กน้อย | `git checkout <ไฟล์>` |
| อัปแล้วพัง | `git reset --hard <commit ก่อนขั้นนั้น>` แล้ว `composer install` |
| พังหนัก | คืนค่า `composer.lock` เดิม → `composer install` → คืน DB dump |
| **ของจริงล่ม** | ระบบจริงไม่เคยถูกแตะ (ทำบนสำเนา) → ไม่ต้องถอย |

---

## 9. สรุปเวลาที่ควรกัน

| ขั้น | เวลาโดยประมาณ | เสี่ยง |
|---|---|---|
| §1 ล้างของไม่ใช้ | 1 ชม. | 🟢 |
| §2 L8 → L9 | ครึ่งวัน | 🟢 |
| §3 L9 → L10 | 1 วัน | 🟡 |
| §4 L10 → L11 (+ ย้าย PHP Apache เป็น 8.2, แปลง `Form::`) | **2–3 วัน** | 🔴 |
| §5 L11 → L12 | ครึ่งวัน | 🟢 |
| ทดสอบรวม | 1 วัน | — |

**รวมประมาณ 5–7 วันทำงาน** — ไม่ควรอัดรวดเดียว แนะนำทำทีละขั้นในวันอังคาร/พฤหัส แล้วปล่อยให้ระบบเดิมทำงานตามปกติระหว่างนั้น

---

## 10. ทางเลือก: ไม่อัปเกรดก็ได้

ถ้าประเมินแล้วไม่คุ้ม สิ่งที่ต้องทำแทนคือ:

- [ ] **ห้ามเปิดระบบออกอินเทอร์เน็ต** (ทำแล้ว 21 ก.ค. 2569 — ปิด Cloudflare tunnel ให้เหลือเฉพาะ `/line/webhook`)
- [ ] คงค่า `APP_DEBUG=false` และ `SESSION_SECURE_COOKIE=true`
- [ ] รัน `composer audit` ทุก 3 เดือน — ถ้ามี CVE ที่ยิงได้จากในวงแลนโดยไม่ต้องล็อกอิน ให้รีบพิจารณาใหม่
- [ ] แก้ PHP ของ Apache กับ CLI ให้เป็นเวอร์ชันเดียวกัน (ทำได้เลย ไม่ต้องรออัป Laravel)
