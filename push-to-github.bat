@echo off
title Push Sparky LMS to GitHub
cd /d "%~dp0"

echo ===================================================
echo Pushing Sparky LMS repository to GitHub...
echo Target: https://github.com/vinaysinbaghel/Sparky-lms-
echo ===================================================
echo.
echo If a GitHub browser window appears, please click "Authorize" to sign in.
echo.

"C:\Users\vinay.baghel\AppData\Local\Programs\Git\cmd\git.exe" push -u origin main --force

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ===================================================
    echo [SUCCESS] Repository successfully pushed to GitHub!
    echo View it at: https://github.com/vinaysinbaghel/Sparky-lms-
    echo ===================================================
) else (
    echo.
    echo [ERROR] Push failed. Please check the error message above.
)

echo.
pause
