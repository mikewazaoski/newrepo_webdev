@echo off
REM Symfony Server Startup Script with SSL Certificate Support
REM This script sets up the required environment variables for Google OAuth2 SSL verification

echo Setting up SSL certificate for OAuth2...
set CURL_CA_BUNDLE=D:\Downloads\php-8.4.16-nts-Win32-vs17-x64\cacert.pem

echo.
echo Starting Symfony development server...
echo CURL_CA_BUNDLE=%CURL_CA_BUNDLE%
echo.

symfony serve:start

pause
