@echo off
REM Double-click this file to run Lukla Operations locally.
REM Requires: PostgreSQL service running (it auto-starts on boot).

cd /d "%~dp0"

echo ============================================
echo   Lukla Operations - local dev server
echo ============================================
echo.
echo   Admin panel : http://127.0.0.1:8000/admin
echo   Login       : admin@lukla.test
echo.
echo   Press Ctrl+C in this window to stop.
echo ============================================
echo.

REM Fail fast with a clear message if PostgreSQL is down.
sc query postgresql-x64-18 | find "RUNNING" >nul
if errorlevel 1 (
    echo [WARNING] PostgreSQL service is NOT running.
    echo           Start it with:  net start postgresql-x64-18
    echo           ^(needs an Administrator terminal^)
    echo.
)

php artisan serve
