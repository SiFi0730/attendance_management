@echo off
REM PHP Built-in Server Startup Script (Windows)

echo ============================================
echo Starting PHP Built-in Server
echo ============================================
echo.
echo Server URL: http://localhost:8080
echo Press Ctrl+C to stop the server
echo.

cd backend\public
php -S localhost:8080 router.php

