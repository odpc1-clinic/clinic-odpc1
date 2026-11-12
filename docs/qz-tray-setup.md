# QZ Tray — พิมพ์เข้าเครื่องที่ระบุแบบเงียบ (ไม่เด้ง dialog)

ใช้กับคลินิกที่มีหลายเครื่องพิมพ์ (Zebra thermal + inkjet) — ให้บัตรคิวยิงเข้า thermal เงียบๆ
ระบบต่อไว้ให้แล้ว (route `/qz/certificate`, `/qz/sign`, ไฟล์ `public/js/qz-print.js`, หน้า `visit/queue_ticket`)
ถ้ายังไม่ตั้ง cert หน้าบัตรจะ fallback ไปเปิด dialog ปกติ (ใช้งานได้ปกติ ไม่พัง)

## ทำครั้งเดียวต่อเครื่องที่ใช้พิมพ์

### 1) วาง qz-tray.js
คัดลอกไฟล์ `qz-tray.js` จากโฟลเดอร์ติดตั้ง QZ Tray
(ปกติอยู่ `C:\Program Files\QZ Tray\demo\js\qz-tray.js` — ถ้าไม่มีโหลดจาก https://qz.io/download)
มาวางที่:
```
D:\xampp\htdocs\public\js\qz-tray.js
```

### 2) สร้าง certificate + private key (ครั้งเดียว ใช้ร่วมได้ทุกเครื่อง)
เปิด Git Bash (หรือที่มี openssl) แล้วรัน:
```bash
MSYS_NO_PATHCONV=1 openssl req -x509 -newkey rsa:2048 -keyout private-key.pem -out digital-certificate.txt -days 3650 -nodes -subj "/CN=Clinic-STI-DPC1"
```
> ใน Git Bash ต้องมี `MSYS_NO_PATHCONV=1` นำหน้า ไม่งั้นมันแปลง `/CN=...` เป็น path Windows แล้ว cert จะพลาด
> (ทางเลือก: ใช้ `-subj "//CN=Clinic-STI-DPC1"` สองสแลชนำหน้าแทน)

ได้ 2 ไฟล์ เอาไปวางที่ (ฝั่งเซิร์ฟเวอร์ Laravel):
```
D:\xampp\htdocs\storage\app\qz\private-key.pem          <- คีย์ลับ ห้ามเผยแพร่
D:\xampp\htdocs\storage\app\qz\digital-certificate.txt  <- ใบรับรอง public
```

### 3) ให้ QZ Tray เชื่อถือ cert นี้ (จะได้ไม่เด้ง "Allow" ทุกครั้ง)
- คัดลอก `digital-certificate.txt` ไปไว้ในเครื่องที่ลง QZ Tray เช่น `C:\QZ\digital-certificate.txt`
- เปิดไฟล์ `C:\Program Files\QZ Tray\qz-tray.properties` (เปิดด้วย Notepad สิทธิ์ Admin)
- เพิ่มบรรทัด:
  ```
  authcert.override=C:/QZ/digital-certificate.txt
  ```
- ออกจาก QZ Tray แล้วเปิดใหม่ (คลิกขวาไอคอนถาด → Exit → เปิดใหม่)

> ถ้าข้ามข้อ 3 ก็ใช้งานได้ แต่จะมีป็อปอัพให้กด "Allow" ทุกครั้งที่พิมพ์

### 4) ตั้งชื่อเครื่องพิมพ์คิว (thermal) ในระบบ
หาชื่อเครื่องเป๊ะๆ ก่อน (ตามที่ Windows ตั้ง เช่น `ZDesigner ZD220-203dpi ZPL`)
แล้วตั้งค่า:
```bash
php artisan tinker --execute="App\Models\Config::setValue('printer_queue','ZDesigner ZD220-203dpi ZPL');"
```
(เว้นว่าง = ใช้เครื่อง default ของ Windows)

## เสร็จแล้วทดสอบ
1. เปิด QZ Tray ให้รันอยู่ (ไอคอนในถาด)
2. กดพิมพ์บัตรคิวจากหน้าทะเบียนผู้ป่วย
3. ควร **ยิงเข้า thermal เงียบๆ ไม่เด้ง dialog**

ถ้าไม่ยิง (QZ ไม่เปิด/ตั้งค่าไม่ครบ) → ระบบจะเปิด dialog ปกติให้เลือกเครื่องแทน

## ต่อยอด (ภายหลัง)
- sticker / OPD card ก็ทำแบบเดียวกันได้ — เพิ่ม config `printer_sticker`, `printer_opd` แล้วเรียก `QZPrint.html(ชื่อเครื่อง, html)` จากหน้านั้น (บอกทีมพัฒนาให้ต่อได้เลย)
