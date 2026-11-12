@echo off
chcp 874 >nul
title ตั้งค่าเปิดฟิล์ม PACS
rem ให้หน้า PACS (http://<PACS_IP>) เปิดโปรแกรม INFINITT Starter ได้เลย ไม่ต้องถามทุกครั้ง
rem ทำไม: PACS เป็น http เบราว์เซอร์จึงถาม "open INFStarter" ทุกครั้งโดยไม่มีช่องจำไว้
rem       แล้วหน้า PACS ปิดตัวเองไปก่อนกด Open ฟิล์มเลยเปิดได้ครั้งเดียว (พบ 2569-09-30)
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo  [!] กรุณาคลิกขวาไฟล์นี้ แล้วเลือก "Run as administrator"
    echo.
    pause
    exit /b
)
echo.
reg query "HKCR\infstarter" >nul 2>&1
if %errorlevel% neq 0 (
    echo  [!] เครื่องนี้ยังไม่ได้ลงโปรแกรม INFINITT Starter
    echo      ดาวน์โหลดติดตั้งก่อนที่ http://<PACS_IP>/InstallStarter.msi
    echo      แล้วค่อยรันไฟล์นี้อีกครั้ง
    echo.
)
echo  กำลังตั้งค่า Chrome และ Edge...
set "POL=[{\"allowed_origins\":[\"http://<PACS_IP>\"],\"protocol\":\"infstarter\"}]"
reg add "HKLM\SOFTWARE\Policies\Google\Chrome" /v AutoLaunchProtocolsFromOrigins /t REG_SZ /d "%POL%" /f >nul
set E1=%errorlevel%
reg add "HKLM\SOFTWARE\Policies\Microsoft\Edge" /v AutoLaunchProtocolsFromOrigins /t REG_SZ /d "%POL%" /f >nul
set E2=%errorlevel%
echo.
if %E1% equ 0 if %E2% equ 0 (
    echo  [OK] ตั้งค่าสำเร็จ!
    echo  กรุณาปิดเบราว์เซอร์ทุกหน้าต่าง แล้วเปิดใหม่
    echo  ตรวจได้ที่ chrome://policy จะเห็น AutoLaunchProtocolsFromOrigins
    goto done
)
echo  [X] ตั้งค่าไม่สำเร็จ กรุณาแจ้งผู้ดูแลระบบ
:done
echo.
pause
