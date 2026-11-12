@echo off
chcp 874 >nul
title ติดตั้งใบรับรองระบบคลินิก
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo  [!] กรุณาคลิกขวาไฟล์นี้ แล้วเลือก "Run as administrator"
    echo.
    pause
    exit /b
)
echo.
echo  กำลังติดตั้งใบรับรองระบบคลินิก...
certutil -addstore -f "ROOT" "%~dp0clinic.crt"
if %errorlevel% equ 0 (
    echo.
    echo  [OK] ติดตั้งสำเร็จ!
    echo  กรุณาปิดเบราว์เซอร์ทุกหน้าต่าง แล้วเปิดใหม่
    echo  จากนั้นเข้า https://<ADMIN_PC_IP> จะไม่มีคำเตือนอีก
) else (
    echo.
    echo  [X] ติดตั้งไม่สำเร็จ กรุณาแจ้งผู้ดูแลระบบ
)
echo.
pause
