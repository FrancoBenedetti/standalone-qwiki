@echo off
REM ==============================================================================
REM Standalone Qwiki - Windows "Send To" Integration Setup
REM Creates a shortcut in shell:sendto enabling right-click "Send to -> Send to Qwiki"
REM ==============================================================================
setlocal EnableDelayedExpansion

echo Setting up "Send to Qwiki" for Windows Explorer...

set "SCRIPT_DIR=%~dp0..\.."
set "PYTHON_CLI=%SCRIPT_DIR%\qwiki-postbox.py"
set "SENDTO_DIR=%APPDATA%\Microsoft\Windows\SendTo"
set "TARGET_BAT=%SENDTO_DIR%\Send to Qwiki.bat"

if not exist "%PYTHON_CLI%" (
    echo Error: Could not locate qwiki-postbox.py at %PYTHON_CLI%
    pause
    exit /b 1
)

(
    echo @echo off
    echo REM Windows Send to Qwiki Launcher
    echo if "%%~1"=="" ^(
    echo     echo No file or directory selected.
    echo     pause
    echo     exit /b 1
    echo ^)
    echo python "%PYTHON_CLI%" send %%* --gui
    echo if %%ERRORLEVEL% NEQ 0 ^(
    echo     echo.
    echo     echo Transfer failed with error code %%ERRORLEVEL%%.
    echo     pause
    echo ^) else ^(
    echo     echo.
    echo     echo Press any key to close this window...
    echo     timeout /t 4 ^>nul
    echo ^)
) > "%TARGET_BAT%"

echo.
echo Success! Installed to: %TARGET_BAT%
echo You can now right-click any Markdown, HTML, or PDF file or folder in File Explorer and choose:
echo   Send to -^> Send to Qwiki
echo.
pause
