@echo off
:: Launches the Apache fix with Administrator rights (UAC prompt)
powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process powershell -Verb RunAs -ArgumentList '-NoProfile -ExecutionPolicy Bypass -File \"\"%~dp0FIX-LOCALHOST-LOGIN.ps1\"\"'"
