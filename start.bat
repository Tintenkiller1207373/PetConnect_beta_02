@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

echo ========================================
echo PetConnect Startskript
echo ========================================
echo.

REM PHP suchen
echo [1/6] Suche PHP...
set "PHP_PATH="
if exist "C:\xampp\php\php.exe" (
    set "PHP_PATH=C:\xampp\php\php.exe"
    echo PHP gefunden: !PHP_PATH!
) else (
    where php >nul 2>&1
    if !errorlevel! == 0 (
        for /f "delims=" %%i in ('where php') do set "PHP_PATH=%%i"
        echo PHP gefunden: !PHP_PATH!
    ) else (
        echo FEHLER: PHP nicht gefunden!
        echo Bitte installiere XAMPP.
        goto fehler
    )
)
echo.

REM Python suchen
echo [2/6] Suche Python...
set "PYTHON_PATH="
for /d %%d in ("C:\Users\%USERNAME%\AppData\Local\Programs\Python\Python*") do (
    if exist "%%d\python.exe" (
        set "PYTHON_PATH=%%d\python.exe"
        echo Python gefunden: !PYTHON_PATH!
        goto python_gefunden
    )
)
where python >nul 2>&1
if !errorlevel! == 0 (
    for /f "delims=" %%i in ('where python') do (
        set "TEMP_PATH=%%i"
        echo !TEMP_PATH! | findstr /i "WindowsApps" >nul
        if !errorlevel! NEQ 0 (
            set "PYTHON_PATH=%%i"
            echo Python gefunden: !PYTHON_PATH!
            goto python_gefunden
        )
    )
)
echo FEHLER: Python nicht gefunden!
echo Bitte installiere Python von python.org.
goto fehler
:python_gefunden
echo.

REM MySQL suchen
echo [3/6] Suche MySQL...
set "MYSQL_PATH="
if exist "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" (
    set "MYSQL_PATH=C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe"
    goto mysql_found
)
where mysql >nul 2>&1
if !errorlevel! == 0 (
    for /f "delims=" %%i in ('where mysql') do (
        set "MYSQL_PATH=%%i"
        goto mysql_found
    )
)
if exist "C:\xampp\mysql\bin\mysql.exe" (
    set "MYSQL_PATH=C:\xampp\mysql\bin\mysql.exe"
    goto mysql_found
)
echo FEHLER: MySQL nicht gefunden!
goto fehler

:mysql_found
echo MySQL gefunden: !MYSQL_PATH!

echo Teste MySQL Verbindung...
"!MYSQL_PATH!" -u root -pFabiKli10 -e "SELECT 1;" >nul 2>&1
if !errorlevel! NEQ 0 (
    echo.
    echo FEHLER: MySQL ist nicht erreichbar!
    echo Bitte starte MySQL im XAMPP Control Panel und versuche es erneut.
    goto fehler
)
echo MySQL ist bereit!
echo.

REM Datenbank pruefen und erstellen
echo [4/6] Pruefe Datenbank...
"!MYSQL_PATH!" -u root -pFabiKli10 -e "CREATE DATABASE IF NOT EXISTS petconnect;"
if !errorlevel! NEQ 0 (
    echo FEHLER: Konnte Datenbank nicht erstellen!
    goto fehler
)
echo Datenbank petconnect ist bereit.

REM Schema importieren falls Tabellen fehlen
echo Pruefe ob Tabellen existieren...
for /f "delims=" %%i in ('"!MYSQL_PATH!" -u root -pFabiKli10 petconnect -sN -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='petconnect' AND table_name='users';"') do set TABLE_COUNT=%%i
if "!TABLE_COUNT!"=="0" (
    echo Tabellen fehlen. Importiere Schema...
    if exist "database\schema.sql" (
        "!MYSQL_PATH!" -u root -pFabiKli10 petconnect < database\schema.sql
        if !errorlevel! == 0 (
            echo Schema erfolgreich importiert.
        ) else (
            echo FEHLER: Schema-Import fehlgeschlagen!
            goto fehler
        )
    ) else (
        echo FEHLER: database\schema.sql nicht gefunden!
        goto fehler
    )
) else (
    echo Tabellen existieren bereits.
)
echo.

REM PHP Server starten
echo [5/6] Starte PHP Server auf Port 8001...
start "PetConnect PHP Server" "!PHP_PATH!" -S localhost:8001 -t "%~dp0frontend" "%~dp0router.php"
echo PHP Server gestartet.
echo Frontend: http://localhost:8001/
echo.

REM AI Service starten
echo [6/6] Starte AI Service auf Port 8002...
cd /d "%~dp0ai-service"
start "PetConnect AI Service" "!PYTHON_PATH!" main.py
echo AI Service gestartet.
echo.

echo ========================================
echo Alle Dienste wurden gestartet!
echo ========================================
echo.
echo Frontend:    http://localhost:8001/
echo AI Service:  http://localhost:8002/
echo.
echo Zum Beenden beide Fenster schliessen.
echo.
pause
exit /b 0

:fehler
echo.
echo ========================================
echo Fehler beim Starten!
echo ========================================
echo.
pause
exit /b 1
